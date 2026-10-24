<?php
namespace App\Services;

use App\Models\{Receipt,Expense,JournalEntry,ChartAccount,CashBankAccount};
use Illuminate\Support\Facades\DB;

/** Only posts source transactions with independently documented counterpart accounts. */
class SafeJournalPosting
{
    public function assess($record): array
    {
        if (!in_array($record->status, ['ACTIVE'], true)) return [false,'Transaksi tidak aktif',null,null];
        if ((float)$record->amount <= 0) return [false,'Nominal tidak positif',null,null];
        if ($record->journal()->exists()) return [false,'Sudah memiliki jurnal',null,null];
        $reference = $record instanceof Receipt ? $record->transaction_no : $record->expense_no;
        if (JournalEntry::where('reference',$reference)->where('status','POSTED')->exists()) return [false,'Sudah ada jurnal dengan nomor referensi ini; perlu pemeriksaan duplikasi',null,null];
        if ($record instanceof Receipt) {
            if ($record->payment_method === 'GUARANTOR') return [false,'Klaim penjamin: menunggu rekonsiliasi/konfirmasi pengakuan piutang',null,null];
            if (!in_array($record->payment_method, ['CASH','TRANSFER','QRIS','CARD'], true)) return [false,'Metode pembayaran kosong atau belum terverifikasi',null,null];
            if (!$record->cash_bank_account_id) return [false,'Rekening kas/bank penerimaan belum dipilih',null,null];
            if (!$record->chart_account_id) return [false,'Akun pendapatan belum dipetakan',null,null];
            $cash = CashBankAccount::with('chartAccount')->find($record->cash_bank_account_id);
            $revenue = ChartAccount::find($record->chart_account_id);
            if (!$cash?->chartAccount || !$revenue || $revenue->type !== 'REVENUE') return [false,'Pemetaan akun kas/bank atau pendapatan tidak valid',null,null];
            if (($record->payment_method === 'CASH' && $cash->type !== 'CASH') || ($record->payment_method !== 'CASH' && $cash->type !== 'BANK')) return [false,'Jenis rekening tidak cocok metode pembayaran',null,null];
            return [true,'Siap dijurnal', $cash->chartAccount->id,$revenue->id];
        }
        if ($record instanceof Expense) {
            if (!$record->cash_bank_account_id || !$record->chart_account_id) return [false,'Rekening atau akun pengeluaran kosong',null,null];
            $cash = CashBankAccount::with('chartAccount')->find($record->cash_bank_account_id);
            $expenseAccount = ChartAccount::find($record->chart_account_id);
            if (!$cash?->chartAccount || !$expenseAccount || !in_array($expenseAccount->type,['EXPENSE','ASSET','LIABILITY','EQUITY'],true)) return [false,'Pemetaan pengeluaran tidak valid',null,null];
            return [true,'Siap dijurnal',$expenseAccount->id,$cash->chartAccount->id];
        }
        return [false,'Jenis data tidak didukung',null,null];
    }

    public function post($record): array
    {
        return DB::transaction(function () use ($record) {
            $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            [$ok,$reason,$debitId,$creditId] = $this->assess($record);
            if (!$ok) return [false,$reason];
            $userId = $record instanceof Receipt ? $record->cashier_id : $record->created_by;
            if (!DB::table('users')->where('id',$userId)->exists()) return [false,'Pembuat transaksi tidak ditemukan'];
            $entry = JournalEntry::create([
                'journal_no' => 'AUTO-'.($record instanceof Receipt?'R':'E').'-'.$record->id,
                'journal_date'=> $record instanceof Receipt ? $record->transaction_date : $record->expense_date,
                'reference'=> $record instanceof Receipt ? $record->transaction_no : $record->expense_no,
                'source_type'=>get_class($record), 'source_id'=>$record->id,
                'description'=>'Jurnal otomatis transaksi #'.$record->id,
                'status'=>'POSTED','created_by'=>$userId,
            ]);
            $amount = $record->amount;
            $entry->lines()->createMany([
                ['chart_account_id'=>$debitId,'debit'=>$amount,'credit'=>0,'description'=>'Posting otomatis debit'],
                ['chart_account_id'=>$creditId,'debit'=>0,'credit'=>$amount,'description'=>'Posting otomatis kredit'],
            ]);
            return [true,'Jurnal '.$entry->journal_no.' berhasil dibuat'];
        });
    }
}
