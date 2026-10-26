<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseTransactionService
{
    public function update(Expense $expense, array $data): void
    {
        DB::transaction(function () use ($expense, $data) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            $journal = $expense->journal()->lockForUpdate()->first();
            $expense->update($data);

            if (!$journal) {
                app(SafeJournalPosting::class)->post($expense);
                return;
            }

            $expense->load(['account', 'cashBankAccount.chartAccount']);
            if (!$expense->account || !$expense->cashBankAccount?->chartAccount) {
                throw ValidationException::withMessages([
                    'cash_bank_account_id' => 'Akun kas/bank belum dipetakan. Jurnal dan pengeluaran belum diubah.',
                ]);
            }

            // Keep the existing journal identity and replace its lines atomically.
            $journal->update([
                'journal_date' => $expense->expense_date,
                'reference' => $expense->expense_no,
                'description' => $expense->description,
            ]);
            $journal->lines()->get()->each->delete();
            $journal->lines()->createMany([
                ['chart_account_id' => $expense->chart_account_id, 'description' => $expense->description, 'debit' => $expense->amount, 'credit' => 0],
                ['chart_account_id' => $expense->cashBankAccount->chartAccount->id, 'description' => $expense->description, 'debit' => 0, 'credit' => $expense->amount],
            ]);
        });
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            foreach ($expense->journal()->lockForUpdate()->get() as $journal) {
                $journal->lines()->get()->each->delete();
                $journal->delete();
            }
            $expense->delete();
        });
    }
}
