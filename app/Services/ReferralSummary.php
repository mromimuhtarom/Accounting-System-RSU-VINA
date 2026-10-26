<?php

namespace App\Services;

use App\Models\{Expense, ReferralRecipient};
use Illuminate\Support\Collection;

class ReferralSummary
{
    public function expenses(string $start, string $end): Collection
    {
        return Expense::with(['referralRecipient', 'patient', 'receipt.patient'])
            ->where('expense_type', 'REFERRAL')->where('status', 'ACTIVE')
            ->whereBetween('expense_date', [$start, $end])->get();
    }

    public function rows(string $start, string $end): Collection
    {
        $masters = ReferralRecipient::all()->keyBy('normalized_name');
        $aliases = \Illuminate\Support\Facades\DB::table('referral_recipient_legacy_names')->pluck('referral_recipient_id', 'name');
        $byId = $masters->keyBy('id');
        return $this->expenses($start, $end)->groupBy(function ($expense) use ($aliases) {
            return $expense->referral_recipient_id
                ?: ($aliases->get($expense->referral_recipient) ?: 'legacy:'.ReferralRecipient::normalizeName($expense->referral_recipient ?? ''));
        })->map(function ($items, $key) use ($byId) {
            $first = $items->first();
            $recipient = $byId->get($key);
            return [
                'recipient_id'=>$recipient?->id, 'name'=>$recipient?->name ?? ($first->referral_recipient ?: 'Penerima historis belum diisi'),
                'transactions'=>$items->count(),
                'patients'=>$items->map(fn ($e) => mb_strtoupper(trim($e->referral_medical_record_no ?: ($e->patient?->medical_record_no ?: $e->receipt?->patient?->medical_record_no ?? ''))))->filter()->unique()->count(),
                'sc'=>$items->where('referral_procedure', 'SC')->count(),
                'ri'=>$items->where('referral_procedure', 'RI')->count(),
                'sc_amount'=>(float) $items->where('referral_procedure', 'SC')->sum('amount'),
                'ri_amount'=>(float) $items->where('referral_procedure', 'RI')->sum('amount'),
                'unclassified'=>$items->whereNotIn('referral_procedure', ['SC', 'RI'])->count(),
                'total'=>(float) $items->sum('amount'),
            ];
        })->sortByDesc('total')->values();
    }

    public function forRecipient(ReferralRecipient $recipient)
    {
        // Legacy rows remain untouched, but continue to belong to their original master after a rename.
        $legacyNames = \Illuminate\Support\Facades\DB::table('referral_recipient_legacy_names')
            ->where('referral_recipient_id', $recipient->id)->pluck('name');
        return Expense::where('expense_type', 'REFERRAL')->where(function ($q) use ($recipient, $legacyNames) {
            $q->where('referral_recipient_id', $recipient->id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('referral_recipient_id')->whereIn('referral_recipient', $legacyNames));
        });
    }
}
