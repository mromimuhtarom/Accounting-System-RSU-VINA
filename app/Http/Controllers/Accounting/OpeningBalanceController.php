<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpeningBalanceRequest;
use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Services\LedgerAmounts;
use App\Services\OpeningBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OpeningBalanceController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', OpeningBalance::class);
        $balance = OpeningBalance::with('lines.account', 'journal')->where('scope', 'INITIAL')->first();
        $accounts = ChartAccount::where('is_active', true)->whereIn('type', ['ASSET', 'LIABILITY', 'EQUITY'])->whereDoesntHave('children')->orderBy('code')->get();
        $formRows = $balance?->lines->map(fn ($l) => ['chart_account_id' => $l->chart_account_id, 'side' => LedgerAmounts::cents($l->debit) > 0 ? 'DEBIT' : 'CREDIT', 'amount' => LedgerAmounts::cents($l->debit) > 0 ? $l->debit : $l->credit])->all() ?? [];
        while (count($formRows) < 6) {
            $formRows[] = ['chart_account_id' => '', 'side' => 'NORMAL', 'amount' => ''];
        }

        return view('accounting.opening-balances.index', ['balance' => $balance, 'accounts' => $accounts, 'formRows' => $formRows,
            'firstJournalDate' => JournalEntry::where('status', 'POSTED')->min('journal_date'),
            'audits' => $balance ? DB::table('opening_balance_audits')->where('opening_balance_id', $balance->id)->orderByDesc('id')->get() : collect()]);
    }

    public function store(OpeningBalanceRequest $request, OpeningBalanceService $service)
    {
        $service->save($request->validated(), $request->user()->id);

        return redirect()->route('opening-balances.index')->with('success', 'Draft saldo awal tersimpan. Belum ada jurnal sampai approval dan posting.');
    }

    public function approve(Request $request, OpeningBalance $balance, OpeningBalanceService $service)
    {
        Gate::authorize('approve', OpeningBalance::class);
        $d = $request->validate(['version' => 'required|integer|min:0', 'verified' => 'required|accepted']);
        $service->approve($balance, (int) $d['version'], $request->user()->id);

        return back()->with('success', 'Saldo awal disetujui. Accounting dapat melakukan posting.');
    }

    public function post(Request $request, OpeningBalance $balance, OpeningBalanceService $service)
    {
        Gate::authorize('manage', OpeningBalance::class);
        $d = $request->validate(['version' => 'required|integer|min:0']);
        $journal = $service->post($balance, (int) $d['version'], $request->user()->id);

        return redirect()->route('reports.financial-position', ['as_of' => $balance->balance_date->format('Y-m-d')])->with('success', 'Saldo awal diposting sebagai jurnal '.$journal->journal_no.'.');
    }

    public function returnToDraft(Request $request, OpeningBalance $balance)
    {
        Gate::authorize('approve', OpeningBalance::class);
        $d = $request->validate(['version' => 'required|integer|min:0', 'reason' => 'required|string|min:5|max:2000']);
        DB::transaction(function () use ($balance, $request, $d) {
            $b = OpeningBalance::lockForUpdate()->findOrFail($balance->id);
            if ($b->status !== 'APPROVED' || $b->version !== (int) $d['version']) {
                throw ValidationException::withMessages(['opening' => 'Hanya approval yang belum diposting dapat dikembalikan ke draft.']);
            }
            $before = $b->toArray();
            $b->update(['status' => 'DRAFT', 'approved_by' => null, 'approved_at' => null, 'version' => $b->version + 1]);
            DB::table('opening_balance_audits')->insert(['opening_balance_id' => $b->id, 'actor_id' => $request->user()->id, 'action' => 'RETURN_TO_DRAFT', 'before' => json_encode($before), 'after' => json_encode($b->toArray() + ['reason' => $d['reason']]), 'created_at' => now()]);
        });

        return back()->with('success','Dikembalikan ke draft untuk dikoreksi.');
    }
}
