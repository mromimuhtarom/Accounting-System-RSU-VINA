<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ReferralRecipient;
use App\Services\{ReferralRecipientService, ReferralSummary, SmartExcelImport, InsuranceExcel};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferralRecipientController extends Controller
{
    public function index(Request $request)
    {
        $query = ReferralRecipient::query()->orderBy('name');
        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
        }
        if (in_array($request->input('active'), ['0', '1'], true)) $query->where('is_active', $request->input('active'));
        return view('accounting.referral-recipients.index', ['recipients'=>$query->paginate(10)->withQueryString()]);
    }

    public function create() { return view('accounting.referral-recipients.form', ['recipient'=>null]); }
    public function edit(ReferralRecipient $recipient) { return view('accounting.referral-recipients.form', compact('recipient')); }

    public function similar(Request $request, ReferralRecipientService $service)
    {
        $data = $request->validate(['name'=>'required|string|max:150', 'ignore'=>'nullable|integer']);
        return response()->json(['recipients'=>$service->similar($data['name'], $data['ignore'] ?? null)->map->only(['id','code','name','is_active'])]);
    }

    public function store(Request $request, ReferralRecipientService $service) { return $this->save($request, $service); }
    public function update(Request $request, ReferralRecipient $recipient, ReferralRecipientService $service) { return $this->save($request, $service, $recipient); }

    private function save(Request $request, ReferralRecipientService $service, ?ReferralRecipient $recipient = null)
    {
        $data = $request->validate(['name'=>'required|string|max:150', 'phone'=>'nullable|string|max:50', 'notes'=>'nullable|string|max:2000', 'is_active'=>'sometimes|boolean', 'confirm_distinct'=>'sometimes|boolean']);
        $similar = $service->similar($data['name'], $recipient?->id);
        if ($similar->isNotEmpty() && !$request->boolean('confirm_distinct')) {
            if ($request->expectsJson()) return response()->json(['message'=>'Nama mirip penerima existing. Pilih penerima existing atau konfirmasi penerima berbeda.', 'recipients'=>$similar->map->only(['id','code','name','is_active'])], 409);
            return back()->withInput()->with('similarRecipients', $similar->map->only(['id','code','name','is_active'])->all());
        }
        $recipient = $service->save($data + ['is_active'=>true], $recipient);
        if ($request->expectsJson()) return response()->json($recipient->only(['id','code','name','is_active']), 201);
        return redirect()->route('accounting.referral-recipients.show', $recipient)->with('success', 'Penerima referral berhasil disimpan.');
    }

    public function show(Request $request, ReferralRecipient $recipient, ReferralSummary $summary)
    {
        $year = max(2000, min(2100, (int) $request->input('year', now()->year)));
        $month = max(0, min(12, (int) $request->input('month', 0)));
        $start = CarbonImmutable::create($year, $month ?: 1, 1);
        $end = $month ? $start->endOfMonth() : $start->endOfYear();
        $rows = $summary->forRecipient($recipient)->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()]);
        $stats = $summary->rows($start->toDateString(), $end->toDateString())->firstWhere('recipient_id', $recipient->id);
        return view('accounting.referral-recipients.show', ['recipient'=>$recipient, 'year'=>$year, 'month'=>$month, 'stats'=>$stats,
            'expenses'=>$rows->with(['account', 'patient', 'receipt.patient'])->latest('expense_date')->latest('id')->paginate(10)->withQueryString()]);
    }

    public function destroy(ReferralRecipient $recipient, ReferralSummary $summary)
    {
        $message = DB::transaction(function () use ($recipient, $summary) {
            $recipient = ReferralRecipient::whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            if ($summary->forRecipient($recipient)->exists()) {
                $recipient->update(['is_active'=>false]);
                return 'Penerima sudah digunakan; status diubah menjadi nonaktif. Riwayat tetap tersimpan.';
            }
            $recipient->delete();
            return 'Penerima yang belum digunakan berhasil dihapus.';
        });
        return redirect()->route('accounting.referral-recipients.index')->with('success', $message);
    }

    public function import(Request $request, SmartExcelImport $excel, ReferralRecipientService $service)
    {
        $request->validate(['file'=>'required|file|mimes:xlsx,xls|max:10240']);
        $rows = $excel->read($request->file('file')->getRealPath());
        // Same title/header convention as the application's Excel exports.
        $header = array_search(true, array_map(fn ($row) => in_array('nama', array_map(fn ($v) => ReferralRecipient::normalizeName((string) $v), $row), true), array_slice($rows, 0, 30)), true);
        if ($header !== false) $rows = array_slice($rows, $header);
        $result = $service->import($rows);
        return back()->with('success', "Import selesai: {$result['created']} penerima baru, {$result['reused']} baris menggunakan data existing.");
    }

    public function template()
    {
        return InsuranceExcel::download('Template_Penerima_Referral.xlsx', ['Nama','Nomor Kontak','Keterangan'], [], 'Isi mulai baris 5; nama existing akan digunakan kembali.');
    }
}
