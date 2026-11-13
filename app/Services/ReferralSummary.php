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
        $groups = [];
        $expenses = Expense::query()->select(['id', 'referral_recipient_id', 'referral_recipient', 'referral_medical_record_no',
            'referral_procedure', 'amount', 'patient_id', 'receipt_id'])
            ->with(['patient:id,medical_record_no', 'receipt:id,patient_id', 'receipt.patient:id,medical_record_no'])
            ->where('expense_type', 'REFERRAL')->where('status', 'ACTIVE')
            ->whereBetween('expense_date', [$start, $end])->lazyById(500);
        foreach ($expenses as $expense) {
            $key = $expense->referral_recipient_id
                ?: ($aliases->get($expense->referral_recipient) ?: 'legacy:'.ReferralRecipient::normalizeName($expense->referral_recipient ?? ''));
            if (!isset($groups[$key])) {
                $recipient = $byId->get($key);
                $groups[$key] = ['recipient_id'=>$recipient?->id, 'name'=>$recipient?->name ?? ($expense->referral_recipient ?: 'Penerima historis belum diisi'),
                    'transactions'=>0, 'patients'=>[], 'sc'=>0, 'ri'=>0, 'sc_amount'=>0, 'ri_amount'=>0, 'unclassified'=>0, 'total'=>0];
            }
            $group = &$groups[$key];
            $group['transactions']++;
            $mrn = mb_strtoupper(trim($expense->referral_medical_record_no ?: ($expense->patient?->medical_record_no ?: $expense->receipt?->patient?->medical_record_no ?? '')));
            if ($mrn) $group['patients'][$mrn] = true;
            $amount = LedgerAmounts::cents($expense->amount);
            $group['total'] += $amount;
            if (in_array($expense->referral_procedure, ['SC', 'RI'], true)) {
                $procedure = strtolower($expense->referral_procedure);
                $group[$procedure]++;
                $group[$procedure.'_amount'] += $amount;
            } else $group['unclassified']++;
            unset($group);
        }
        return collect($groups)->map(function ($group) {
            $group['patients'] = count($group['patients']);
            foreach (['total', 'sc_amount', 'ri_amount'] as $field) $group[$field] = $group[$field] / 100;
            return $group;
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
