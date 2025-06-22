<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillOfLadingController;
use App\Http\Controllers\InvoicePDFController;
use App\Filament\Pages\BulkScannerAssignment;

Route::get('/', function () {
    return view('welcome');
});

Route::get('admin/shipments/{shipment}/bill-of-lading', [BillOfLadingController::class, 'generateBillOfLading'])
    ->name('shipments.bill-of-lading');

Route::post('admin/shipments/bills-of-lading', [BillOfLadingController::class, 'generateMultipleBills'])
    ->name('shipments.bills-of-lading');

Route::get('/bill-of-lading/{shipment}', [BillOfLadingController::class, 'generateBillOfLading'])
    ->name('bill-of-lading.generate');

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/admin/bulk-scanner-assignment/check-tracking', function() {
        $page = new BulkScannerAssignment();
        $page->mount();
        return $page->checkTrackingNumber();
    })->name('admin.scanner.check-tracking');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/invoice/{invoice}/pdf', [InvoicePDFController::class, 'generatePDF'])->name('invoice.pdf');
    Route::get('/invoice/{invoice}/stream', [InvoicePDFController::class, 'streamPDF'])->name('invoice.stream');
    Route::get('/invoice/{invoice}/preview', [InvoicePDFController::class, 'previewPDF'])->name('invoice.preview');
});
