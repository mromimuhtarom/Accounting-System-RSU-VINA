<?php

// Explicit, one-off maintenance operation. Default mode is read-only.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{CashBankAccount, Receipt};
use Illuminate\Support\Facades\DB;

$options = getopt('', ['apply', 'bank-id:', 'expected-count:']);
$bank = CashBankAccount::with('chartAccount')->findOrFail((int)($options['bank-id'] ?? 0));
if (!$bank->is_active || $bank->type !== 'BANK' || !$bank->chartAccount?->is_active || $bank->chartAccount->type !== 'ASSET') {
    throw new RuntimeException('Rekening bank atau COA bank tidak valid.');
}
$summary = Receipt::where('payment_method','GUARANTOR')->selectRaw('COUNT(*) AS count, COALESCE(SUM(amount),0) AS total')->first();
echo json_encode(['mode'=>isset($options['apply']) ? 'apply' : 'dry-run', 'bank'=>$bank->name, 'count'=>$summary->count, 'total'=>$summary->total]).PHP_EOL;
if (!isset($options['apply'])) exit(0);
if (!isset($options['expected-count']) || (int)$options['expected-count'] < 1) throw new RuntimeException('expected-count wajib ditentukan untuk perubahan massal.');

$batch = 'guarantor-to-bank-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
$directory = storage_path('app/private/maintenance');
if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('Folder cadangan tidak dapat dibuat.');
if (!file_exists($directory.'/.htaccess') && file_put_contents($directory.'/.htaccess', "Require all denied\n") === false) throw new RuntimeException('Folder cadangan tidak dapat dilindungi.');
$backupPath = $directory.'/'.$batch.'.jsonl';

$result = DB::transaction(function () use ($bank, $options, $batch, $backupPath) {
    $rows = DB::table('receipts')->where('payment_method','GUARANTOR')->orderBy('id')->lockForUpdate()
        ->get(['id','transaction_no','payment_method','cash_bank_account_id','amount','cashier_id','updated_at']);
    if ($rows->count() !== (int)$options['expected-count']) throw new RuntimeException('Jumlah transaksi berubah; operasi dibatalkan.');
    $ids = $rows->pluck('id')->all();
    if (DB::table('bpjs_claim_payments')->whereIn('receipt_id',$ids)->exists()) throw new RuntimeException('Ada tautan pembayaran BPJS; perlu pemeriksaan tersendiri.');
    if (DB::table('journal_entries')->where(fn ($q) => $q->where(fn ($source) => $source->where('source_type',(new Receipt)->getMorphClass())->whereIn('source_id',$ids))
        ->orWhereIn('reference',$rows->pluck('transaction_no')->all()))->exists()) throw new RuntimeException('Ada jurnal terkait; perlu pemeriksaan tersendiri.');

    $handle = fopen($backupPath,'xb');
    if (!$handle) throw new RuntimeException('Cadangan tidak dapat dibuka.');
    try {
        foreach ($rows as $row) {
            $line = json_encode($row,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
            if (fwrite($handle,$line) !== strlen($line)) throw new RuntimeException('Penulisan cadangan tidak lengkap.');
        }
        if (!fflush($handle)) throw new RuntimeException('Cadangan belum tersimpan.');
    } finally { fclose($handle); }

    $banks = CashBankAccount::pluck('name','id');
    $at = now('UTC')->format('Y-m-d H:i:s');
    $changed = 0;
    foreach ($rows->chunk(500) as $chunk) {
        $updated = DB::table('receipts')->whereIn('id',$chunk->pluck('id')->all())->where('payment_method','GUARANTOR')
            ->update(['payment_method'=>'TRANSFER','cash_bank_account_id'=>$bank->id,'updated_at'=>$at]);
        if ($updated !== $chunk->count()) throw new RuntimeException('Tidak semua transaksi dapat diperbarui; seluruh perubahan dibatalkan.');
        $logs = [];
        foreach ($chunk as $row) {
            $before = ['transaction_no'=>$row->transaction_no,'amount'=>$row->amount,'cashier_id'=>$row->cashier_id,
                'payment_method'=>$row->payment_method,'cash_bank_account_id'=>$row->cash_bank_account_id ? ['id'=>$row->cash_bank_account_id,'nama'=>$banks[$row->cash_bank_account_id] ?? null] : null];
            $after = array_replace($before,['payment_method'=>'TRANSFER','cash_bank_account_id'=>['id'=>$bank->id,'nama'=>$bank->name]]);
            $logs[] = [
                'actor_id'=>null,'actor_name'=>'Sistem — koreksi massal atas permintaan pengguna','actor_email'=>null,'actor_roles'=>'SYSTEM',
                'action'=>'updated','menu'=>'Penerimaan','form_name'=>'Koreksi Metode Pembayaran','route_name'=>'maintenance.'.$batch,
                'subject_type'=>Receipt::class,'subject_id'=>(string)$row->id,'subject_label'=>$row->transaction_no,
                'before_values'=>json_encode($before,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'after_values'=>json_encode($after,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'changed_fields'=>json_encode(['payment_method','cash_bank_account_id']), 'ip_address'=>null,'occurred_at'=>$at,
            ];
        }
        DB::table('activity_logs')->insert($logs);
        $changed += $updated;
    }
    if (DB::table('receipts')->whereIn('id',$ids)->where(fn ($q) => $q->where('payment_method','!=','TRANSFER')->orWhere('cash_bank_account_id','!=',$bank->id)->orWhereNull('cash_bank_account_id'))->exists()) {
        throw new RuntimeException('Verifikasi hasil tidak sesuai.');
    }
    return ['changed'=>$changed,'audit_logs'=>count($ids),'batch'=>$batch,'backup'=>$backupPath,'backup_sha256'=>hash_file('sha256',$backupPath)];
});
echo json_encode($result,JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
