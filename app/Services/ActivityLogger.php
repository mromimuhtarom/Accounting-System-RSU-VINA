<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivityLogger
{
    public const FORMS = [
        'AssistantProfile'=>'Profil Asisten',
        'ReferralRecipient'=>'Penerima Referral', 'Receipt'=>'Penerimaan', 'Expense'=>'Pengeluaran', 'Patient'=>'Data Pasien',
        'ChartAccount'=>'Daftar Akun (COA)', 'ServiceUnit'=>'Unit Pelayanan', 'Guarantor'=>'Penjamin',
        'CashBankAccount'=>'Kas & Bank', 'ExpenseCategory'=>'Kategori Pengeluaran', 'FinancialGroup'=>'Group Laporan',
        'InsuranceMonthlyClaim'=>'Klaim & Piutang Asuransi', 'BpjsClaim'=>'Klaim BPJS',
        'BpjsClaimEvent'=>'Riwayat Klaim BPJS', 'BpjsClaimPayment'=>'Pembayaran Klaim BPJS',
        'JournalEntry'=>'Jurnal', 'JournalLine'=>'Baris Jurnal', 'User'=>'Manajemen Akun',
    ];

    public function record(Model $model, string $action, ?array $before, ?array $after, array $changed): void
    {
        $actor = auth()->user();
        if (!$actor || !Schema::hasTable('activity_logs')) return;
        $changed = array_values(array_diff($changed, ['updated_at', 'created_at', 'last_login_at', 'remember_token']));
        if ($action === 'updated' && $changed === []) return;

        $type = class_basename($model);
        $form = self::FORMS[$type] ?? $type;
        if ($type === 'Expense' && $model->getAttribute('expense_type') === 'REFERRAL') $form = 'Pengeluaran Referal';
        $menu = match ($type) {
            'AssistantProfile'=>'Pengaturan Asisten',
            'User' => 'Manajemen Akun',
            'ReferralRecipient', 'ChartAccount', 'ServiceUnit', 'Guarantor', 'CashBankAccount', 'ExpenseCategory', 'FinancialGroup' => 'Master Data',
            'JournalEntry', 'JournalLine' => 'Jurnal',
            'InsuranceMonthlyClaim', 'BpjsClaim', 'BpjsClaimEvent', 'BpjsClaimPayment' => 'Klaim & Piutang Asuransi',
            default => $form,
        };
        $label = $model->getAttribute('expense_no') ?? $model->getAttribute('transaction_no')
            ?? $model->getAttribute('journal_no') ?? $model->getAttribute('name') ?? $model->getAttribute('sep_no')
            ?? $form.' #'.$model->getKey();
        ActivityLog::create([
            'actor_id'=>$actor->id, 'actor_name'=>$actor->name, 'actor_email'=>$actor->email,
            'actor_roles'=>$actor->roles()->pluck('code')->implode(', '), 'action'=>$action, 'menu'=>$menu,
            'form_name'=>$form, 'route_name'=>request()->route()?->getName(),
            'subject_type'=>get_class($model), 'subject_id'=>(string) $model->getKey(), 'subject_label'=>$label,
            'before_values'=>$this->snapshot($before), 'after_values'=>$this->snapshot($after),
            'changed_fields'=>$changed, 'ip_address'=>request()->ip(), 'occurred_at'=>now('UTC'),
        ]);
    }

    private function snapshot(?array $attributes): ?array
    {
        if ($attributes === null) return null;
        foreach (['remember_token', 'last_login_at', 'two_factor_secret', 'two_factor_recovery_codes'] as $secret) unset($attributes[$secret]);
        if (array_key_exists('password', $attributes)) $attributes['password'] = '[Disembunyikan]';
        $references = [
            'created_by'=>['users','name'], 'updated_by'=>['users','name'], 'cashier_id'=>['users','name'],
            'chart_account_id'=>['chart_accounts','name'], 'expense_account_id'=>['chart_accounts','name'],
            'revenue_account_id'=>['chart_accounts','name'], 'cash_bank_account_id'=>['cash_bank_accounts','name'],
            'referral_recipient_id'=>['referral_recipients','name'], 'guarantor_id'=>['guarantors','name'], 'expense_category_id'=>['expense_categories','name'],
            'service_unit_id'=>['service_units','name'], 'patient_id'=>['patients','name'],
            'receipt_id'=>['receipts','transaction_no'], 'journal_entry_id'=>['journal_entries','journal_no'],
            'financial_group_id'=>['financial_groups','name'],
        ];
        foreach ($references as $field => [$table, $column]) {
            if (!empty($attributes[$field]) && Schema::hasTable($table)) {
                $name = DB::table($table)->where('id', $attributes[$field])->value($column);
                if ($name !== null) $attributes[$field] = ['id'=>$attributes[$field], 'nama'=>$name];
            }
        }
        return $attributes;
    }
}
