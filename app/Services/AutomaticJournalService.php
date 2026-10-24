<?php
namespace App\Services;

use App\Models\Expense;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomaticJournalService
{
    public function receipt(Receipt $receipt, int $userId): bool
    {
        if ($receipt->journal()->exists() || $receipt->status !== 'ACTIVE') return false;
        // Buku kas lama sering tidak mencantumkan sumber Kas/Bank dan unit layanan.
        // Jangan menebak akun debit; klaim BPJS yang belum cair tidak boleh dianggap kas.
        if (!$receipt->service_unit_id || !$receipt->cash_bank_account_id ||
            !in_array($receipt->payment_method, ['CASH','TRANSFER','QRIS','CARD'], true)) return false;
        if (DB::table('bpjs_claim_payments')->where('receipt_id', $receipt->id)->exists()) return false;
        try { app(JournalService::class)->fromReceipt($receipt, $userId); return true; }
        catch (ValidationException $e) { return false; }
    }

    public function expense(Expense $expense, int $userId): bool
    {
        if ($expense->journal()->exists() || $expense->status !== 'ACTIVE') return false;
        if (!$expense->chart_account_id || !$expense->cash_bank_account_id) return false;
        try { app(JournalService::class)->fromExpense($expense, $userId); return true; }
        catch (ValidationException $e) { return false; }
    }
}
