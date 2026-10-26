<?php

use App\Http\Controllers\Accounting\ExpenseController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\MasterController;
use App\Http\Controllers\Accounting\ReceiptController as AccountingReceiptController;
use App\Http\Controllers\Accounting\ReferralExpenseController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Cashier\PatientController;
use App\Http\Controllers\Cashier\ReceiptController as CashierReceiptController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IT\UserController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InsuranceClaimController;
use App\Http\Controllers\InsuranceExcelController;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/session/activity', fn () => response()->noContent())->name('session.activity');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:KASIR,ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING')->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/referral-recipients/similar', [\App\Http\Controllers\Accounting\ReferralRecipientController::class, 'similar'])->name('referral-recipients.similar');
        Route::get('/referral-recipients/template', [\App\Http\Controllers\Accounting\ReferralRecipientController::class, 'template'])->name('referral-recipients.template');
        Route::post('/referral-recipients/import', [\App\Http\Controllers\Accounting\ReferralRecipientController::class, 'import'])->name('referral-recipients.import');
        Route::resource('referral-recipients', \App\Http\Controllers\Accounting\ReferralRecipientController::class)->parameters(['referral-recipients'=>'recipient']);
        Route::match(['get', 'post'], '/referrals/import', [\App\Http\Controllers\Accounting\ExcelImportController::class, 'handle'])->defaults('module', 'referrals')->name('referrals.import');
        Route::get('/referrals/excel', [ReferralExpenseController::class, 'export'])->name('referrals.export');
        Route::get('/referrals/{referral}/attachment', [ReferralExpenseController::class, 'attachment'])->name('referrals.attachment');
        Route::resource('referrals', ReferralExpenseController::class)->except(['show']);
        Route::post('/referrals/{referral}/journal', [ReferralExpenseController::class, 'generateJournal'])->name('referrals.journal');
    });

    // Kasir dapat melihat seluruh penerimaan dan pengeluaran serta mengelola pengeluaran umum.
    Route::middleware('role:KASIR')->prefix('kasir')->name('cashier.')->group(function () {
        Route::match(['get', 'post'], '/excel-import/{module}', [\App\Http\Controllers\Accounting\ExcelImportController::class, 'handle'])->whereIn('module', ['receipts', 'expenses'])->name('excel-import');
        Route::resource('pasien', PatientController::class)->except(['show'])->parameters(['pasien' => 'patient'])->names([
            'index' => 'patients.index',
            'create' => 'patients.create',
            'store' => 'patients.store',
            'edit' => 'patients.edit',
            'update' => 'patients.update',
            'destroy' => 'patients.destroy',
        ]);

        Route::get('/penerimaan', [CashierReceiptController::class, 'index'])->name('receipts.index');
        Route::get('/penerimaan/input', [CashierReceiptController::class, 'create'])->name('receipts.create');
        Route::post('/penerimaan', [CashierReceiptController::class, 'store'])->name('receipts.store');
        Route::get('/penerimaan/{receipt}/edit', [CashierReceiptController::class, 'edit'])->name('receipts.edit');
        Route::put('/penerimaan/{receipt}', [CashierReceiptController::class, 'update'])->name('receipts.update');
        Route::delete('/penerimaan/{receipt}', [CashierReceiptController::class, 'destroy'])->name('receipts.destroy');

        Route::get('/pengeluaran', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/pengeluaran/input', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/pengeluaran', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/pengeluaran/{pengeluaran}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('/pengeluaran/{pengeluaran}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/pengeluaran/{pengeluaran}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    Route::middleware('role:ASURANSI')->prefix('asuransi/penerimaan')->name('insurance.receipts.')->group(function () {
        Route::match(['get', 'post'], '/excel-import', [\App\Http\Controllers\Accounting\ExcelImportController::class, 'handle'])->defaults('module', 'receipts')->name('excel-import');
        Route::get('/', [CashierReceiptController::class, 'index'])->name('index');
        Route::get('/input', [CashierReceiptController::class, 'create'])->name('create');
        Route::post('/', [CashierReceiptController::class, 'store'])->name('store');
        Route::get('/{receipt}/edit', [CashierReceiptController::class, 'edit'])->name('edit');
        Route::put('/{receipt}', [CashierReceiptController::class, 'update'])->name('update');
        Route::delete('/{receipt}', [CashierReceiptController::class, 'destroy'])->name('destroy');
    });

    // Kasir, Accounting, dan IT dapat mengelola Master Data.
    Route::middleware('role:KASIR,ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING,ASURANSI')
        ->prefix('accounting')
        ->name('accounting.')
        ->group(function () {
            Route::get('/master/{type}', [MasterController::class, 'index'])->name('master.index');
            Route::post('/master/{type}', [MasterController::class, 'store'])->middleware('role:KASIR,ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING,ASURANSI')->name('master.store');
            Route::put('/master/{type}/{id}', [MasterController::class, 'update'])->middleware('role:KASIR,ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING,ASURANSI')->name('master.update');
            Route::delete('/master/{type}/{id}', [MasterController::class, 'destroy'])->middleware('role:KASIR,ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING,ASURANSI')->name('master.destroy');
        });

    Route::middleware('role:ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING')->prefix('accounting')->name('accounting.')->group(function () {
        Route::match(['get', 'post'], '/excel-import/{module}', [\App\Http\Controllers\Accounting\ExcelImportController::class, 'handle'])->whereIn('module', ['receipts', 'expenses'])->name('excel-import');
        Route::get('/penerimaan', [AccountingReceiptController::class, 'index'])->name('receipts.index');
        Route::get('/penerimaan/input', [AccountingReceiptController::class, 'create'])->name('receipts.create');
        Route::post('/penerimaan', [AccountingReceiptController::class, 'store'])->name('receipts.store');
        Route::get('/penerimaan/{receipt}/edit', [AccountingReceiptController::class, 'edit'])->name('receipts.edit');
        Route::put('/penerimaan/{receipt}', [AccountingReceiptController::class, 'update'])->name('receipts.update');
        Route::delete('/penerimaan/{receipt}', [AccountingReceiptController::class, 'destroy'])->middleware('role:ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING')->name('receipts.destroy');
        Route::post('/penerimaan/{receipt}/journal', [AccountingReceiptController::class, 'generateJournal'])->middleware('role:ACCOUNTING,KEPALA_KEUANGAN')->name('receipts.journal');

        Route::resource('pengeluaran', ExpenseController::class)->except(['show','destroy']);
        Route::delete('/pengeluaran/{pengeluaran}', [ExpenseController::class, 'destroy'])->middleware('role:ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING')->name('pengeluaran.destroy');
        Route::post('/pengeluaran/{expense}/journal', [ExpenseController::class, 'generateJournal'])->middleware('role:ACCOUNTING,KEPALA_KEUANGAN')->name('pengeluaran.journal');


        Route::resource('jurnal', JournalController::class)->only(['index', 'create', 'show']);
        Route::post('/jurnal', [JournalController::class,'store'])->middleware('role:ACCOUNTING,KEPALA_KEUANGAN')->name('jurnal.store');
    });

    Route::middleware('role:IT')->group(function () {
        Route::prefix('it')->name('it.')->group(function () {
            Route::get('/activity-logs', [\App\Http\Controllers\IT\ActivityLogController::class, 'index'])->name('activity-logs.index');
            Route::get('/activity-logs/{activityLog}', [\App\Http\Controllers\IT\ActivityLogController::class, 'show'])->name('activity-logs.show');
            Route::get('/session-logs', [\App\Http\Controllers\IT\ActivityLogController::class, 'index'])->name('session-logs.index');
            Route::get('/session-logs/{activityLog}', [\App\Http\Controllers\IT\ActivityLogController::class, 'show'])->name('session-logs.show');
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
        });
    });

    Route::middleware('role:ACCOUNTING,ADMIN_ACCOUNTING,ATASAN,FINANCE,KEPALA_KEUANGAN,DIREKTUR,OWNER')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/bulanan', [ReportController::class, 'monthly'])->name('monthly');
        Route::get('/tahunan', [ReportController::class, 'yearly'])->name('yearly');
        Route::get('/penerimaan', [ReportController::class, 'income'])->name('income');
        Route::get('/pengeluaran', [ReportController::class, 'expense'])->name('expense');
        Route::get('/bulanan/rincian', [ReportController::class, 'monthlyDetail'])->name('monthly.detail');
    });
    Route::middleware('role:ASURANSI,ACCOUNTING,ADMIN_ACCOUNTING,KEPALA_KEUANGAN,OWNER,DIREKTUR')->prefix('klaim-asuransi')->name('insurance.')->group(function(){
        Route::get('/',[InsuranceClaimController::class,'index'])->name('index');
        Route::get('/excel',[InsuranceExcelController::class,'claims'])->name('export');
        Route::get('/input',[InsuranceClaimController::class,'create'])->name('create');
        Route::post('/',[InsuranceClaimController::class,'store'])->name('store');
        Route::get('/{claim}/edit',[InsuranceClaimController::class,'form'])->name('edit');
        Route::put('/{claim}',[InsuranceClaimController::class,'update'])->name('update');
        Route::post('/{claim}/selesai',[InsuranceClaimController::class,'finish'])->name('finish');
        Route::delete('/{claim}',[InsuranceClaimController::class,'destroy'])->name('destroy');
    });
    Route::middleware('role:KASIR,ACCOUNTING,ADMIN_ACCOUNTING,KEPALA_KEUANGAN,OWNER,DIREKTUR')->get('/excel/penerimaan',[InsuranceExcelController::class,'receipts'])->name('excel.receipts');
    Route::middleware('role:KASIR,ACCOUNTING,ADMIN_ACCOUNTING,KEPALA_KEUANGAN,OWNER,DIREKTUR')->get('/excel/pengeluaran',[InsuranceExcelController::class,'expenses'])->name('excel.expenses');
});
