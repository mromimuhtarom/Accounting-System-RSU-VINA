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
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Kasir: kelola pasien, penerimaan miliknya, dan pengeluaran umum miliknya.
    Route::middleware('role:KASIR')->prefix('kasir')->name('cashier.')->group(function () {
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

    // Kasir, Accounting, dan IT dapat mengelola Master Data.
    Route::middleware('role:KASIR,ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING,ASURANSI')
        ->prefix('accounting')
        ->name('accounting.')
        ->group(function () {
            Route::get('/master/{type}', [MasterController::class, 'index'])->name('master.index');
            Route::post('/master/{type}', [MasterController::class, 'store'])->middleware('role:KASIR,ACCOUNTING,KEPALA_KEUANGAN,ASURANSI')->name('master.store');
            Route::put('/master/{type}/{id}', [MasterController::class, 'update'])->middleware('role:KASIR,ACCOUNTING,KEPALA_KEUANGAN,ASURANSI')->name('master.update');
            Route::delete('/master/{type}/{id}', [MasterController::class, 'destroy'])->middleware('role:KASIR,ACCOUNTING,KEPALA_KEUANGAN,ASURANSI')->name('master.destroy');
        });

    Route::middleware('role:ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING')->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/penerimaan', [AccountingReceiptController::class, 'index'])->name('receipts.index');
        Route::get('/penerimaan/input', [AccountingReceiptController::class, 'create'])->name('receipts.create');
        Route::post('/penerimaan', [AccountingReceiptController::class, 'store'])->name('receipts.store');
        Route::get('/penerimaan/{receipt}/edit', [AccountingReceiptController::class, 'edit'])->name('receipts.edit');
        Route::put('/penerimaan/{receipt}', [AccountingReceiptController::class, 'update'])->name('receipts.update');
        Route::delete('/penerimaan/{receipt}', [AccountingReceiptController::class, 'destroy'])->middleware('role:ACCOUNTING,KEPALA_KEUANGAN,ADMIN_ACCOUNTING')->name('receipts.destroy');
        Route::post('/penerimaan/{receipt}/journal', [AccountingReceiptController::class, 'generateJournal'])->middleware('role:ACCOUNTING,KEPALA_KEUANGAN')->name('receipts.journal');

        Route::resource('pengeluaran', ExpenseController::class)->except(['show','destroy']);
        Route::delete('/pengeluaran/{pengeluaran}', [ExpenseController::class, 'destroy'])->middleware('role:ACCOUNTING,KEPALA_KEUANGAN')->name('pengeluaran.destroy');
        Route::post('/pengeluaran/{expense}/journal', [ExpenseController::class, 'generateJournal'])->middleware('role:ACCOUNTING,KEPALA_KEUANGAN')->name('pengeluaran.journal');


        Route::resource('jurnal', JournalController::class)->only(['index', 'create', 'show']);
        Route::post('/jurnal', [JournalController::class,'store'])->middleware('role:ACCOUNTING,KEPALA_KEUANGAN')->name('jurnal.store');
    });

    Route::middleware('role:IT')->group(function () {
        Route::prefix('it')->name('it.')->group(function () {
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
