<?php

namespace App\Services;

use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalService
{
    public function fromReceipt(Receipt $receipt, int $userId): JournalEntry
    {
        if ($receipt->status !== 'ACTIVE') {
            throw ValidationException::withMessages(['journal' => 'Penerimaan tidak aktif.']);
        }
        if ($receipt->journal()->exists()) {
            throw ValidationException::withMessages(['journal' => 'Transaksi ini sudah memiliki jurnal.']);
        }

        if (\Illuminate\Support\Facades\DB::table('bpjs_claim_payments')->where('receipt_id', $receipt->id)->exists()) {
            throw ValidationException::withMessages(['journal'=>'Pelunasan klaim BPJS perlu jurnal Bank/Piutang BPJS, bukan jurnal pendapatan.']);
        }
        $receipt->load(['serviceUnit.revenueAccount', 'cashBankAccount.chartAccount', 'guarantor']);

        if (!$receipt->service_unit_id || (!$receipt->cash_bank_account_id && !$receipt->guarantor_id)) {
            throw ValidationException::withMessages([
                'journal' => 'Pemasukan format buku kas belum memiliki mapping akun otomatis. Gunakan Jurnal Manual untuk transaksi ini.',
            ]);
        }

        $revenue = $receipt->serviceUnit?->revenueAccount;
        if (!$revenue) throw ValidationException::withMessages(['journal'=>'Revenue account unit pelayanan belum diset.']);

        $debitAccount = $receipt->cashBankAccount?->chartAccount;
        if ($receipt->payment_method === 'GUARANTOR') {
            $code = $receipt->guarantor?->is_bpjs ? config('accounting.bpjs_receivable_account_code') : config('accounting.receivable_account_code');
            $debitAccount = ChartAccount::where('code', $code)->first();
        }
        if (!$debitAccount) throw ValidationException::withMessages(['journal'=>'Account debit belum tersedia.']);

        return DB::transaction(function () use ($receipt, $userId, $debitAccount, $revenue) {
            if ($receipt->journal()->exists()) return $receipt->journal;
            $journal = $receipt->journal()->create([
                'journal_no' => $this->number('JRN'),
                'journal_date' => $receipt->transaction_date,
                'reference' => $receipt->transaction_no,
                'description' => 'Pemasukan '.($receipt->notes ?: ($receipt->patient?->name ?? $receipt->patient_name ?? $receipt->transaction_no)),
                'status' => 'POSTED',
                'created_by' => $userId,
            ]);
            $journal->lines()->createMany([
                ['chart_account_id'=>$debitAccount->id,'description'=>'Penerimaan '.$receipt->transaction_no,'debit'=>$receipt->amount,'credit'=>0],
                ['chart_account_id'=>$revenue->id,'description'=>'Pendapatan '.$receipt->serviceUnit->name,'debit'=>0,'credit'=>$receipt->amount],
            ]);
            return $journal;
        });
    }

    public function fromExpense(Expense $expense, int $userId): JournalEntry
    {
        if ($expense->status !== 'ACTIVE') throw ValidationException::withMessages(['journal'=>'Pengeluaran tidak aktif.']);
        if ($expense->journal()->exists()) throw ValidationException::withMessages(['journal'=>'Pengeluaran ini sudah memiliki jurnal.']);
        $expense->load(['account','cashBankAccount.chartAccount']);
        if (!$expense->account || !$expense->cashBankAccount?->chartAccount) throw ValidationException::withMessages(['journal'=>'Mapping account pengeluaran / kas bank belum lengkap.']);

        return DB::transaction(function () use ($expense,$userId) {
            if ($expense->journal()->exists()) return $expense->journal;
            $journal = $expense->journal()->create([
                'journal_no'=>$this->number('JRN'), 'journal_date'=>$expense->expense_date, 'reference'=>$expense->expense_no,
                'description'=>$expense->description, 'status'=>'POSTED','created_by'=>$userId,
            ]);
            $journal->lines()->createMany([
                ['chart_account_id'=>$expense->account->id,'description'=>$expense->description,'debit'=>$expense->amount,'credit'=>0],
                ['chart_account_id'=>$expense->cashBankAccount->chartAccount->id,'description'=>$expense->description,'debit'=>0,'credit'=>$expense->amount],
            ]);
            return $journal;
        });
    }

    public function manual(array $data, int $userId): JournalEntry
    {
        foreach ($data['lines'] as $line) {
            $lineDebit = (float) ($line['debit'] ?? 0);
            $lineCredit = (float) ($line['credit'] ?? 0);
            if (($lineDebit > 0 && $lineCredit > 0) || ($lineDebit <= 0 && $lineCredit <= 0)) {
                throw ValidationException::withMessages(['lines'=>'Setiap baris jurnal harus berisi salah satu: debit atau credit.']);
            }
        }
        $debit = collect($data['lines'])->sum(fn($l)=>(float)($l['debit']??0));
        $credit = collect($data['lines'])->sum(fn($l)=>(float)($l['credit']??0));
        if (round($debit,2) !== round($credit,2) || $debit <= 0) {
            throw ValidationException::withMessages(['lines'=>'Total debit dan credit harus sama dan lebih dari 0.']);
        }
        return DB::transaction(function () use ($data,$userId) {
            $journal=JournalEntry::create([
                'journal_no'=>$this->number('JRN'),'journal_date'=>$data['journal_date'],'reference'=>$data['reference']??null,
                'description'=>$data['description'],'status'=>'POSTED','created_by'=>$userId,
            ]);
            foreach($data['lines'] as $line){$journal->lines()->create($line);} return $journal;
        });
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.random_int(100,999);
    }
}
