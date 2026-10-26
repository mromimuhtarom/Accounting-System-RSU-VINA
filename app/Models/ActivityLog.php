<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array', 'changed_fields' => 'array', 'occurred_at' => 'datetime'];
    }

    public function actionLabel(): string
    {
        return ['created' => 'Tambah', 'updated' => 'Edit', 'deleted' => 'Hapus', 'restored' => 'Pulihkan', 'login'=>'Login', 'logout'=>'Logout', 'logout_auto'=>'Logout Otomatis'][$this->action] ?? $this->action;
    }

    public static function fieldLabel(string $field): string
    {
        return [
            'name'=>'Nama', 'email'=>'Email', 'password'=>'Kata sandi', 'roles'=>'Role / Hak akses',
            'amount'=>'Nominal', 'description'=>'Keterangan', 'notes'=>'Catatan', 'status'=>'Status',
            'expense_date'=>'Tanggal pengeluaran', 'transaction_date'=>'Tanggal penerimaan', 'journal_date'=>'Tanggal jurnal',
            'expense_no'=>'Nomor pengeluaran', 'transaction_no'=>'Nomor penerimaan', 'journal_no'=>'Nomor jurnal',
            'created_by'=>'Akun penginput', 'updated_by'=>'Akun pengubah', 'cashier_id'=>'Petugas kasir',
            'chart_account_id'=>'Akun COA', 'cash_bank_account_id'=>'Kas / Bank', 'guarantor_id'=>'Penjamin',
            'expense_category_id'=>'Kategori pengeluaran', 'is_active'=>'Status aktif',
            'session_status'=>'Status sesi', 'logged_in_at'=>'Waktu login (WIB)', 'logged_out_at'=>'Waktu logout (WIB)',
            'last_activity_at'=>'Aktivitas terakhir (WIB)', 'expires_at'=>'Batas sesi (WIB)', 'logout_reason'=>'Alasan logout',
            'device_name'=>'Perangkat / Browser', 'login_ip'=>'IP saat login',
        ][$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    public static function displayValue(mixed $value): string
    {
        if ($value === null) return '—';
        if (is_bool($value)) return $value ? 'Ya' : 'Tidak';
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return (string) $value;
    }
}
