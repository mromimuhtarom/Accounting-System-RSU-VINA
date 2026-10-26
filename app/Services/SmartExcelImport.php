<?php

namespace App\Services;

use App\Http\Controllers\Accounting\ExpenseController;
use App\Http\Controllers\Accounting\ReceiptController;
use App\Models\CashBankAccount;
use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Guarantor;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class SmartExcelImport
{
    public const MAX_ROWS = 5000;

    public function fields(string $module): array
    {
        if ($module === 'referrals') return [
            'expense_date'=>'Tanggal|Tanggal Transaksi|Date', 'referral_recipient_id'=>'Penerima Referral|Nama Penerima|Penerima Referal',
            'referral_patient_name'=>'Nama Pasien|Pasien', 'referral_medical_record_no'=>'No RM|Nomor RM',
            'referral_procedure'=>'Tindakan|Jenis Tindakan|SC RI', 'amount'=>'Nominal|Nominal Referral|Jumlah',
            'description'=>'Keterangan|Deskripsi', 'chart_account_id'=>'COA|Kode COA|COA Referral',
            'payment_method'=>'Metode Pembayaran|Metode', 'cash_bank_account_id'=>'Kas Bank|Kas/Bank', 'expense_no'=>'Nomor Transaksi|No Transaksi',
        ];
        $common = ['amount' => 'Nominal|Jumlah|Total|Nilai', 'payment_method' => 'Metode Pembayaran|Payment Method', 'cash_bank_account_id' => 'Kas Bank|Rekening|Akun Kas|Cash Bank Account', 'guarantor_id' => 'Penjamin|Guarantor', 'service_type' => 'Jenis Layanan|Service Type'];

        return $module === 'receipts'
            ? array_merge(['transaction_date' => 'Tanggal|Tanggal Transaksi|Date', 'chart_account_id' => 'Akun|Kode COA|COA|Akun Pendapatan', 'notes' => 'Keterangan|Deskripsi|Description', 'doctor_name' => 'Dokter', 'hd' => 'HD', 'rs' => 'RS', 'transaction_no' => 'Nomor Transaksi|No Transaksi|Transaction No'], $common)
            : array_merge(['expense_date' => 'Tanggal|Tanggal Transaksi|Date', 'expense_category_id' => 'Kategori|Kategori Pengeluaran|Category', 'description' => 'Keterangan|Deskripsi|Description', 'reference_no' => 'Referensi|No Referensi', 'expense_no' => 'Nomor Transaksi|No Transaksi|Expense No'], array_diff_key($common, array_flip(['guarantor_id', 'service_type'])));
    }

    public function read(string $path): array
    {
        try {
            $type = IOFactory::identify($path, [IOFactory::READER_XLS, IOFactory::READER_XLSX]);
            if ($type === IOFactory::READER_XLSX) {
                $zip = new \ZipArchive;
                if ($zip->open($path) !== true) {
                    throw new \RuntimeException('Arsip Excel rusak.');
                }
                try {
                    $size = 0;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $size += $zip->statIndex($i)['size'];
                    }
                    if ($size > 64 * 1024 * 1024) {
                        throw new \RuntimeException('Ukuran Excel setelah ekstraksi melebihi 64 MB.');
                    }
                } finally {
                    $zip->close();
                }
            }
            $reader = IOFactory::createReader($type);
            $info = $reader->listWorksheetInfo($path)[0];
            if ($info['totalRows'] > self::MAX_ROWS + 30 || $info['totalColumns'] > 80) {
                throw new \RuntimeException('Maksimal 5.000 baris data dan 80 kolom per file.');
            }
            $reader->setLoadSheetsOnly($info['worksheetName']);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
            // Read literal values only: never evaluate formulas or external links.
            $rows = $book->getSheet(0)->toArray(null, false, false, false);
            $book->disconnectWorksheets();

            return $rows;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => 'File tidak dapat dibaca sebagai Excel .xlsx/.xls. '.$e->getMessage()]);
        }
    }

    public function normalize($value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $value)));
    }

    public function mapping(array $headers, string $module): array
    {
        $mapping = [];
        $used = [];
        foreach ($headers as $i => $header) {
            foreach ($this->fields($module) as $field => $aliases) {
                if (! isset($used[$field]) && in_array($this->normalize($header), array_map($this->normalize(...), array_merge([$field], explode('|', $aliases))), true)) {
                    $mapping[$i] = $field;
                    $used[$field] = true;
                    break;
                }
            }
        }

        return $mapping;
    }

    public function masters(string $module): array
    {
        if ($module === 'referrals') return [
            'referral_recipient_id'=>\App\Models\ReferralRecipient::where('is_active', true)->get(),
            'chart_account_id'=>ChartAccount::where('is_active', true)->where('type', 'EXPENSE')->where('is_referral', true)->get(),
            'cash_bank_account_id'=>CashBankAccount::where('is_active', true)->get(),
        ];
        return $module === 'receipts' ? [
            'chart_account_id' => ChartAccount::where('is_active', true)->where('type', 'REVENUE')->get(),
            'cash_bank_account_id' => CashBankAccount::where('is_active', true)->get(),
            'guarantor_id' => request()->routeIs('insurance.receipts.*')
                ? Guarantor::whereIn('category', Guarantor::INSURANCE_CATEGORIES)->get()
                : Guarantor::all(),
        ] : [
            'expense_category_id' => ExpenseCategory::where('is_active', true)->where('code', '!=', 'REFERAL')->whereHas('expenseAccount', fn ($q) => $q->where('is_active', true)->where('type', 'EXPENSE'))->get(),
            'cash_bank_account_id' => CashBankAccount::where('is_active', true)->get(),
        ];
    }

    public function infer(array $headers, array $rows, string $module): array
    {
        $mapping = $this->mapping($headers, $module);
        $dateField = $module === 'receipts' ? 'transaction_date' : 'expense_date';
        $candidates = [$dateField => [], 'amount' => []];
        foreach ($headers as $column => $header) {
            if (isset($mapping[$column])) {
                continue;
            }
            $values = array_values(array_filter(array_column(array_slice($rows, 0, 20), $column), fn ($v) => $v !== null && $v !== ''));
            if (! $values) {
                continue;
            }
            $dates = $amounts = 0;
            foreach ($values as $value) {
                // Plain numbers can be IDs or Excel dates; do not infer dates from numbers.
                try {
                    if (! is_numeric($value)) {
                        $this->date($value);
                        $dates++;
                    }
                } catch (\Throwable) {
                }
                try {
                    $this->amount($value);
                    $amounts++;
                } catch (\Throwable) {
                }
            }
            if ($dates === count($values)) {
                $candidates[$dateField][] = $column;
            } elseif ($amounts === count($values)) {
                $candidates['amount'][] = $column;
            }
        }
        foreach ($candidates as $field => $columns) {
            if (! in_array($field, $mapping, true) && count($columns) === 1) {
                $mapping[$columns[0]] = $field;
            }
        }

        return $mapping;
    }

    public function date($value): string
    {
        if (is_numeric($value) && $value > 0 && $value < 100000) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, (string) $value);
            if ($date && $date->format($format) === (string) $value) {
                return $date->format('Y-m-d');
            }
        }
        throw new \RuntimeException('Tanggal tidak valid; gunakan YYYY-MM-DD atau DD/MM/YYYY.');
    }

    public function amount($value): string
    {
        if (is_int($value) || is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }
        $value = trim(preg_replace('/^(Rp\.?|IDR)\s*/i', '', trim((string) $value)));
        $value = str_replace(' ', '', $value);
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $value)) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        } elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $value)) {
            $value = str_replace(',', '', $value);
        } elseif (preg_match('/^\d+,\d{1,2}$/', $value)) {
            $value = str_replace(',', '.', $value);
        }
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value) || (float) $value <= 0 || (float) $value > 999999999999.99) {
            throw new \RuntimeException('Nominal tidak valid atau melebihi batas.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    public function preview(array $rows, array $mapping, string $module, Request $request, array $overrides = []): array
    {
        $fields = $this->fields($module);
        $masters = $this->masters($module);
        $result = [];
        $seen = [];
        $mapped = array_filter($mapping, fn ($v) => $v !== null && $v !== '');
        if (count($mapped) !== count(array_unique($mapped)) || array_diff($mapped, array_keys($fields))) {
            throw ValidationException::withMessages(['mapping' => 'Setiap field hanya boleh dipetakan satu kali.']);
        }
        foreach ($rows as $index => $raw) {
            if (! array_filter($raw, fn ($v) => $v !== null && trim((string) $v) !== '')) {
                continue;
            }
            $data = [];
            $errors = [];
            $warnings = [];
            foreach ($mapped as $column => $field) {
                $data[$field] = trim((string) ($raw[$column] ?? ''));
            }
            try {
                $dateField = $module === 'receipts' ? 'transaction_date' : 'expense_date';
                $data[$dateField] = $this->date($data[$dateField] ?? '');
                $data['amount'] = $this->amount($data['amount'] ?? '');
                foreach ($masters as $field => $records) {
                    if (($data[$field] ?? '') === '' && empty($overrides[$index][$field])) {
                        continue;
                    }
                    $value = $overrides[$index][$field] ?? $data[$field];
                    // IDs and unique codes take precedence; normalized names require one match.
                    $matches = $records->filter(fn ($r) => (string) $r->id === (string) $value);
                    if ($matches->isEmpty()) {
                        $matches = $records->filter(fn ($r) => $this->normalize($r->code) === $this->normalize($value));
                    }
                    if ($matches->isEmpty()) {
                        $matches = $records->filter(fn ($r) => ($field === 'referral_recipient_id' ? \App\Models\ReferralRecipient::normalizeName($r->name) === \App\Models\ReferralRecipient::normalizeName((string) $value) : $this->normalize($r->name) === $this->normalize($value)));
                    }
                    if ($matches->count() !== 1) {
                        throw new \RuntimeException($field.': tidak ditemukan atau ambigu; pilih data master pada baris ini.');
                    }
                    $data[$field] = $matches->first()->id;
                }
                if (isset($data['payment_method'])) {
                    $data['payment_method'] = strtoupper($data['payment_method']);
                }
                $rowRequest = Request::create($request->path(), 'POST', $data);
                $rowRequest->setUserResolver(fn () => $request->user());
                $rowRequest->setRouteResolver(fn () => $request->route());
                if ($module === 'referrals') $data['referral_procedure'] = strtoupper($data['referral_procedure'] ?? '');
                $controller = $module === 'expenses' ? app(ExpenseController::class) : app(ReceiptController::class);
                if ($module === 'receipts' && $request->routeIs('cashier.*', 'insurance.receipts.*')) {
                    $controller = app(\App\Http\Controllers\Cashier\ReceiptController::class);
                }
                if ($module === 'referrals') $controller = app(\App\Http\Controllers\Accounting\ReferralExpenseController::class);
                $validated = in_array($module, ['expenses', 'referrals'], true) ? $controller->validated($rowRequest) : $controller->validatedData($rowRequest);
                $extra = Validator::make($data, [
                    'payment_method' => ['nullable', 'in:CASH,TRANSFER,QRIS,CARD,GUARANTOR'],
                    'cash_bank_account_id' => ['nullable', 'exists:cash_bank_accounts,id'],
                    'guarantor_id' => ['nullable', 'exists:guarantors,id'],
                    'service_type' => ['nullable', 'in:RAWAT_JALAN,RAWAT_INAP,MCU,LAINNYA'],
                    'transaction_no' => ['nullable', 'string', 'max:100'], 'expense_no' => ['nullable', 'string', 'max:100'],
                ])->validate();
                $data = array_merge($validated, $extra);
                if ($module === 'receipts' && empty($data['payment_method'])) {
                    $warnings[] = 'Metode pembayaran kosong; jurnal menunggu kelengkapan.';
                }
            } catch (ValidationException $e) {
                $errors = $e->validator->errors()->all();
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
            $key = hash('sha256', json_encode($data));
            $duplicate = ! $errors && (isset($seen[$key]) || $this->duplicate($data, $module));
            if (! $errors) {
                $seen[$key] = true;
            }
            $result[$index] = ['raw' => $raw, 'data' => $data, 'status' => $errors ? 'Error' : ($duplicate ? 'Duplicate' : ($warnings ? 'Warning' : 'Valid')), 'messages' => array_merge($errors, $warnings)];
        }

        return $result;
    }

    public function duplicate(array $data, string $module): bool
    {
        $receipt = $module === 'receipts';
        $model = $receipt ? Receipt::query() : Expense::query();
        $number = $receipt ? 'transaction_no' : 'expense_no';
        if (! empty($data[$number]) && (clone $model)->where($number, $data[$number])->exists()) {
            return true;
        }
        if ($module === 'referrals') {
            foreach (['expense_date','referral_recipient_id','referral_medical_record_no','referral_procedure','amount','cash_bank_account_id'] as $key) $model->where($key, $data[$key] ?? null);
            return $model->where('expense_type', 'REFERRAL')->exists();
        }
        $keys = $receipt ? ['transaction_date', 'chart_account_id', 'notes', 'amount'] : ['expense_date', 'expense_category_id', 'description', 'amount', 'cash_bank_account_id'];
        foreach ($keys as $key) {
            $model->where($key, $data[$key] ?? null);
        }

        return $model->exists();
    }
}
