<?php

use App\Http\Controllers\Accounting\OpeningBalanceController;
use App\Http\Controllers\Accounting\ReceiptController;
use App\Http\Controllers\AccountingWorkspaceController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialPositionController;
use App\Http\Controllers\IT\AssistantProfileController;
use App\Http\Controllers\ReportController;
use App\Models\User;
use App\Services\AccountingAnalysis;
use App\Services\AccountingEntry;
use App\Services\AssetAccounting;
use App\Services\AssistantProfileSettings;
use App\Services\FinancialPosition;
use App\Services\FinancialSources;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Generates read-only local browser fixtures from the real application/database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
auth()->setUser(User::findOrFail(6));
view()->share('errors', new ViewErrorBag);
$directory = sys_get_temp_dir().'/rsuve-responsive-ui-'.basename(base_path());
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$public = 'file:///'.str_replace('\\', '/', public_path()).'/';
$fixtures = [
    'assistant-profile' => ['it.assistant-profile.edit', '/it/asisten/profil', fn ($r) => app(AssistantProfileController::class)->edit(app(AssistantProfileSettings::class))],
    'accounting-workspace' => ['accounting.workspace', '/accounting/ruang-kerja', fn ($r) => app(AccountingWorkspaceController::class)->index($r, app(FinancialSources::class), app(FinancialPosition::class))],
    'accounting-entry' => ['accounting.entries.create', '/accounting/pencatatan-nonkas?kind=expense_accrual', fn ($r) => app(AccountingWorkspaceController::class)->create($r, app(AccountingEntry::class))],
    'accounting-analysis' => ['accounting.analysis', '/reports/analisis-saldo', fn ($r) => app(AccountingWorkspaceController::class)->analysis($r, app(AccountingAnalysis::class))],
    'financial-sources' => ['reports.financial-sources', '/reports/sumber-keuangan', fn ($r) => app(FinancialPositionController::class)->sources($r, app(FinancialSources::class), app(FinancialPosition::class))],
    'receipts' => ['accounting.receipts.index', '/accounting/receipts', fn ($r) => app(ReceiptController::class)->index($r)],
    'create' => ['assets.create', '/aktiva/input', fn ($r) => app(AssetController::class)->create()],
    'inventory' => ['assets.index', '/aktiva', fn ($r) => app(AssetController::class)->index($r, app(AssetAccounting::class))],
    'dashboard' => ['dashboard', '/dashboard?tahun=2026', fn ($r) => app(DashboardController::class)->__invoke($r)],
    'yearly' => ['reports.yearly', '/laporan/tahunan?year=2026', fn ($r) => app(ReportController::class)->yearly($r)],
    'opening' => ['opening-balances.index', '/accounting/saldo-awal', fn ($r) => app(OpeningBalanceController::class)->index()],
    'financial-position' => ['reports.financial-position', '/reports/neraca', fn ($r) => app(FinancialPositionController::class)->index($r, app(FinancialPosition::class))],
];
foreach ($fixtures as $name => [$routeName,$uri,$render]) {
    auth()->setUser($name === 'assistant-profile' ? User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('code', 'IT'))->firstOrFail() : User::findOrFail(6));
    $request = Request::create('http://localhost'.$uri);
    $route = app('router')->getRoutes()->getByName($routeName);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => auth()->user());
    app()->instance('request', $request);
    app('url')->setRequest($request);
    $html = $render($request)->render();
    $html = preg_replace('~https?://[^"\s]+/public/~', $public, $html);
    file_put_contents($directory.'/'.$name.'.html', $html);
}
echo 'Fixtures: '.$directory.PHP_EOL;
