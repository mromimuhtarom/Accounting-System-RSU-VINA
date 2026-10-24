<?php
use App\Http\Controllers\Accounting\BpjsClaimController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','auth','role:CASEMIX,ACCOUNTING,ADMIN_ACCOUNTING,FINANCE,ATASAN,KEPALA_KEUANGAN,DIREKTUR,OWNER'])
    ->prefix('accounting/piutang-bpjs')->name('accounting.bpjs.')->group(function () {
        Route::get('/',[BpjsClaimController::class,'index'])->name('index');
        Route::get('/baru',[BpjsClaimController::class,'create'])->middleware('role:CASEMIX')->name('create');
        Route::post('/',[BpjsClaimController::class,'store'])->middleware('role:CASEMIX')->name('store');
        Route::get('/{claim}',[BpjsClaimController::class,'show'])->name('show');
        Route::post('/{claim}/verifikasi',[BpjsClaimController::class,'verify'])->middleware('role:CASEMIX')->name('verify');
        Route::post('/{claim}/bayar',[BpjsClaimController::class,'pay'])->middleware('role:FINANCE,KEPALA_KEUANGAN')->name('pay');
    });
