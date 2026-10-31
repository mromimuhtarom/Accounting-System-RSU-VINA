<?php

use App\Models\ChartAccount;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
foreach (['insurance_monthly_claims', 'bpjs_claims', 'journal_entries', 'receipts', 'expenses', 'assets', 'opening_balances'] as $table) {
    if (! Schema::hasTable($table)) {
        continue;
    }
    $q = DB::table($table);
    $out = ['table' => $table, 'count' => (clone $q)->count()];
    if ($table === 'insurance_monthly_claims') {
        $out['totals'] = $q->selectRaw('SUM(claimed_amount) claimed, SUM(approved_amount) approved, SUM(paid_amount) paid')->first();
        $out['statuses'] = DB::table($table)->select('status')->selectRaw('COUNT(*) total')->groupBy('status')->get();
    }
    if ($table === 'journal_entries') {
        $out['range'] = $q->selectRaw('MIN(journal_date) first_date, MAX(journal_date) last_date')->first();
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE).PHP_EOL;
}
echo json_encode(['accounts' => ChartAccount::select('type')->selectRaw('COUNT(*) total')->groupBy('type')->get()]).PHP_EOL;
