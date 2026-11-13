<?php

use App\Http\Controllers\AirConditionerQrController;
use App\Http\Controllers\InvoicePdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')
    ->prefix('admin/air-conditioners')
    ->name('admin.air-conditioners.')
    ->group(function (): void {
        Route::get('/scan/{assetCode}', [AirConditionerQrController::class, 'lookup'])->name('scan');
        Route::get('/{airConditioner}/print-qr', [AirConditionerQrController::class, 'print'])->name('print-qr');
    });

Route::middleware('auth')
    ->prefix('admin/invoices')
    ->name('admin.invoices.')
    ->group(function (): void {
        Route::get('/{invoice}/pdf', [InvoicePdfController::class, 'invoice'])->name('invoice');
        Route::get('/{invoice}/bast', [InvoicePdfController::class, 'bast'])->name('bast');
    });
