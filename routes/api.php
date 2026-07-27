<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FieldController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\RescheduleController;
use App\Http\Controllers\Admin\CancelController;
use App\Http\Controllers\Admin\ClosedBookingsController;
use App\Http\Controllers\Admin\RefundOverpaymentController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Tenant\Payment\DuitkuController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\AttributeRentalController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\Treasure\GajiController;
use App\Http\Controllers\Owner\KaryawanController;
use App\Http\Controllers\Owner\UnduhLaporanController;

Route::post('/duitku/callback', [DuitkuController::class, 'callback']);

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello from Laravel!']);
});

Route::post('/login', [AuthController::class, 'login']);

Route::prefix('admin')->middleware(['auth:sanctum', 'check.field.admin'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'dashboard']);
    Route::get('/profile', [AuthController::class, 'profile']);

    Route::get('/list-field', [FieldController::class, 'index']);
    Route::get('/detail-field/{field_id}', [FieldController::class, 'show']);
    Route::post('/update-field', [FieldController::class, 'update']);
    Route::get('/check-slot-availability/{field_id}/{date}', [FieldController::class, 'checkAvailability']);
    Route::post('/close-field', [FieldController::class, 'closeField']);

    Route::get('/list-booking', [BookingController::class, 'index']);
    Route::post('/create-booking', [BookingController::class, 'store']);
    Route::get('/detail-booking/{detail_booking_id}', [BookingController::class, 'show']);

    Route::post('/reschedule-booking/{detail_booking_id}', RescheduleController::class);
    Route::post('/cancel-booking/{detail_booking_id}', CancelController::class);
    Route::get('/list-close-booking', ClosedBookingsController::class);
    Route::post('/refund-overpayment/{id}', RefundOverpaymentController::class);

    Route::post('/payment-booking', [PaymentController::class, 'processPayment']);

    Route::get('/list-attribute', [AttributeController::class, 'index']);
    Route::get('/attribute-types', [AttributeController::class, 'getTypes']);
    Route::get('/detail-attribute/{id}', [AttributeController::class, 'show']);
    Route::post('/create-attribute', [AttributeController::class, 'store']);
    Route::post('/update-attribute/{id}', [AttributeController::class, 'update']);
    Route::post('/delete-attribute/{id}', [AttributeController::class, 'destroy']);
    Route::post('/toggle-attribute-status/{id}', [AttributeController::class, 'toggleStatus']);
    Route::get('/active-bookings', [AttributeRentalController::class, 'getActiveBookings']);

    Route::post('/rent-attribute', [AttributeRentalController::class, 'store']);
    Route::post('/return-rent-attribute/{id}', [AttributeRentalController::class, 'returnItem']);
    Route::get('/detail-rent-attribute/{id}', [AttributeRentalController::class, 'show']);
    Route::get('/history-rent-attribute', [AttributeRentalController::class, 'history']);

    Route::get('/list-expense', [ExpenseController::class, 'listExpense']);
    Route::post('/create-expense', [ExpenseController::class, 'addExpense']);
    Route::post('/update-expense/{id}', [ExpenseController::class, 'updateExpense']);
    Route::post('/delete-expense/{id}', [ExpenseController::class, 'destroy']);
    Route::get('/expense-categories', [ExpenseController::class, 'getCategories']);
});

Route::prefix('treasurer')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/gaji', [GajiController::class, 'index']);
    Route::post('/gaji/update', [GajiController::class, 'update']);
    Route::post('/gaji/sync', [GajiController::class, 'sync']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('owner')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/karyawan', [KaryawanController::class, 'index']);
    Route::post('/karyawan', [KaryawanController::class, 'store']);
    Route::post('/karyawan/{id}/update', [KaryawanController::class, 'update']);
    Route::post('/karyawan/{id}/delete', [KaryawanController::class, 'destroy']);

    Route::get('/laporan-pdf/preview', [UnduhLaporanController::class, 'preview']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::get('/laporan-bulanan', [LaporanController::class, 'index'])->middleware('auth:sanctum');

Route::get('/owner/laporan-pdf/download', [UnduhLaporanController::class, 'download'])
        ->name('owner.laporan.download')
        ->middleware('signed');
