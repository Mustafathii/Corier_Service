<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
use App\Http\Controllers\BillOfLadingController;

Route::get('admin/shipments/{shipment}/bill-of-lading', [BillOfLadingController::class, 'generateBillOfLading'])
    ->name('shipments.bill-of-lading');

Route::post('admin/shipments/bills-of-lading', [BillOfLadingController::class, 'generateMultipleBills'])
    ->name('shipments.bills-of-lading');

Route::get('/bill-of-lading/{shipment}', [App\Http\Controllers\BillOfLadingController::class, 'generateBillOfLading'])
    ->name('bill-of-lading.generate');
