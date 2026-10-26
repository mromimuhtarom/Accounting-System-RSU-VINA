<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Receipt;
use App\Services\AutomaticJournalService;
use App\Services\InsuranceExcel;
use App\Services\SmartExcelImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExcelImportController extends Controller
{
    public function handle(Request $request, string $module, SmartExcelImport $service)
    {
        abort_unless(in_array($module, ['receipts', 'expenses', 'referrals'], true), 404);
        $scope = $request->routeIs('insurance.receipts.*') ? 'insurance' : ($request->routeIs('cashier.*') ? 'cashier' : 'accounting');
        abort_if($scope === 'insurance' && $module !== 'receipts', 403);
        $returnRoute = $module === 'referrals' ? 'accounting.referrals.index' : match ($scope) {
            'insurance' => 'insurance.receipts.index',
            'cashier' => $module === 'receipts' ? 'cashier.receipts.index' : 'cashier.expenses.index',
            default => $module === 'receipts' ? 'accounting.receipts.index' : 'accounting.pengeluaran.index',
        };
        $fields = $service->fields($module);
        if ($request->isMethod('get') && $request->boolean('template')) {
            return InsuranceExcel::download('template-'.$module.'.xlsx', array_keys($fields), [], 'Template Import: isi mulai baris 5; master menggunakan ID, kode, atau nama.');
        }
        $preview = [];
        $rows = [];
        $headers = [];
        $mapping = [];
        $overrides = [];
        $token = null;
        $result = null;
        $store = Cache::store('file');
        if ($request->isMethod('post')) {
            $action = $request->validate(['action' => 'required|in:upload,preview,confirm'])['action'];
            if ($action === 'upload') {
                $request->validate(['file' => 'required|file|extensions:xlsx,xls|max:10240', 'header_row' => 'nullable|integer|min:1|max:30']);
                $all = $service->read($request->file('file')->getRealPath());
                $headerIndex = $request->filled('header_row') ? $request->integer('header_row') - 1 : 0;
                if (! $request->filled('header_row')) {
                    $best = 0;
                    foreach (array_slice($all, 0, 30, true) as $i => $candidate) {
                        $score = count($service->mapping($candidate, $module));
                        if ($score > $best) {
                            $best = $score;
                            $headerIndex = $i;
                        }
                    }
                }
                $headers = $all[$headerIndex] ?? [];
                $rows = array_slice($all, $headerIndex + 1, null, true);
                if (count($rows) > SmartExcelImport::MAX_ROWS || ! $headers || ! $rows) {
                    throw ValidationException::withMessages(['file' => 'File kosong atau melebihi 5.000 baris.']);
                }
                $mapping = $service->infer($headers, $rows, $module);
                $token = (string) Str::uuid();
                $store->put('excel-import:'.$token, ['user' => $request->user()->id, 'module' => $module, 'scope' => $scope, 'rows' => $rows, 'headers' => $headers, 'mapping' => $mapping], now()->addMinutes(30));
            } else {
                // Keep large previews below PHP's max_input_vars limit.
                foreach (['overrides', 'skip'] as $field) {
                    if ($request->filled($field.'_json')) {
                        $decoded = json_decode($request->input($field.'_json'), true);
                        abort_unless(is_array($decoded), 422, 'Data koreksi tidak valid.');
                        $request->merge([$field => $decoded]);
                    }
                }
                $request->validate(['token' => 'required|uuid', 'mapping' => 'required|array', 'mapping.*' => 'nullable|string', 'overrides' => 'nullable|array', 'overrides.*' => 'array', 'overrides.*.*' => 'nullable|integer', 'skip' => 'nullable|array', 'skip.*' => 'integer']);
                $token = $request->string('token')->toString();
                $batch = $store->get('excel-import:'.$token);
                abort_unless($batch && $batch['user'] === $request->user()->id && $batch['module'] === $module && ($batch['scope'] ?? null) === $scope, 419, 'Sesi impor kedaluwarsa. Unggah kembali.');
                $rows = $batch['rows'];
                $headers = $batch['headers'];
                $mapping = $request->input('mapping', []);
                $overrides = $request->input('overrides', []);
                if ($action === 'confirm') {
                    // Only confirm the exact server-side preview reviewed by this user.
                    $signature = $this->signature($mapping, $overrides);
                    abort_unless(($batch['signature'] ?? null) === $signature, 422, 'Mapping berubah. Jalankan preview kembali.');
                    $result = $store->lock('excel-import-write', 1800)->block(10, function () use ($store, $token, $request, $service, $module, $rows, $mapping, $overrides) {
                        abort_unless($store->has('excel-import:'.$token), 419);
                        $result = ['success' => 0, 'failed' => 0, 'skipped' => 0];
                        try {
                            DB::transaction(function () use (&$result, $service, $module, $rows, $mapping, $overrides, $request) {
                                $checked = $service->preview($rows, $mapping, $module, $request, $overrides);
                                foreach ($checked as $i => $row) {
                                    if (in_array((string) $i, array_map('strval', $request->input('skip', [])), true) || $row['status'] === 'Duplicate') {
                                        $result['skipped']++;

                                        continue;
                                    }
                                    if ($row['status'] === 'Error') {
                                        $result['failed']++;

                                        continue;
                                    }
                                    $data = $row['data'];
                                    if ($service->duplicate($data, $module)) {
                                        $result['skipped']++;

                                        continue;
                                    }
                                    $data['status'] = 'ACTIVE';
                                    if ($module === 'receipts') {
                                        $data['transaction_no'] = $data['transaction_no'] ?? 'RCV-IMP-'.Str::uuid();
                                        $data['cashier_id'] = $request->user()->id;
                                        $record = Receipt::create($data);
                                        app(AutomaticJournalService::class)->receipt($record, $request->user()->id);
                                    } elseif ($module === 'referrals') {
                                        $referralRequest = Request::create('/accounting/referrals', 'POST', $data);
                                        $referralRequest->setUserResolver(fn () => $request->user());
                                        app(ReferralExpenseController::class)->store($referralRequest);
                                    } else {
                                        $data['expense_no'] = $data['expense_no'] ?? 'EXP-IMP-'.Str::uuid();
                                        $data['expense_type'] = 'GENERAL';
                                        $data['created_by'] = $request->user()->id;
                                        Expense::create($data);
                                    }
                                    $result['success']++;
                                }
                            });
                        } catch (\Throwable $e) {
                            report($e);
                            throw ValidationException::withMessages(['import' => 'Import dibatalkan seluruhnya; tidak ada transaksi tersimpan. Periksa pemetaan dan coba kembali.']);
                        }
                        $store->forget('excel-import:'.$token);

                        return $result;
                    });
                }
            }
            if (! $result) {
                $preview = $service->preview($rows, $mapping, $module, $request, $overrides);
                $batch = $store->get('excel-import:'.$token);
                $batch['signature'] = $this->signature($mapping, $overrides);
                $store->put('excel-import:'.$token, $batch, now()->addMinutes(30));
            }
        }

        return view('accounting.imports.excel', compact('module', 'fields', 'headers', 'mapping', 'preview', 'token', 'result', 'overrides', 'returnRoute') + ['masters' => $service->masters($module)]);
    }

    private function signature(array $mapping, array $overrides): string
    {
        $mapping = array_filter($mapping, fn ($v) => $v !== null && $v !== '');
        foreach ($overrides as $i => $values) {
            $overrides[$i] = array_filter($values, fn ($v) => $v !== null && $v !== '');
            ksort($overrides[$i]);
        }
        $overrides = array_filter($overrides);
        ksort($mapping);
        ksort($overrides);

        return hash('sha256',json_encode([$mapping, $overrides]));
    }
}
