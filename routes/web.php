<?php

use App\Http\Controllers\Tenant\Auth\AuthController;
use App\Http\Controllers\Tenant\Auth\ProfileController;
use App\Http\Controllers\Tenant\Auth\RegisterController;
use App\Http\Controllers\Tenant\Booking\BookingController;
use App\Http\Controllers\Tenant\Booking\CancelledDetailBookingController;
use App\Http\Controllers\Tenant\Booking\HistoryController;
use App\Http\Controllers\Tenant\Booking\RescheduleDetailBookingController;
use App\Http\Controllers\Tenant\Booking\ScheduleController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Middleware\CheckTenantRole;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\NotificationController;

// Pengalihan Halaman Utama ke Dashboard Tenant
Route::get('/', function () {
    return redirect()->route('tenant.booking.dashboard');
});

// Grup Otorisasi untuk Pengunjung yang Belum Login (Guest Only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

// Grup Otorisasi untuk Pengguna yang Sudah Login (Auth)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Filter Khusus Pengguna dengan Peran Tenant (Penyewa)
    Route::middleware(CheckTenantRole::class)->group(function () {
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

        // Modul Operasional Pemesanan Lapangan (Tenant Booking System)
        Route::prefix('tenant/booking')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('tenant.booking.dashboard');
            Route::get('/transactions', [HistoryController::class, 'index'])->name('tenant.booking.transaction');
            Route::get('/transactions/history/{id}', [HistoryController::class, 'show'])->name('tenant.booking.history.show');
            Route::get('/fetch-slots', [ScheduleController::class, 'fetchSlots'])->name('tenant.booking.fetch-slots');
            Route::get('/create-form', [BookingController::class, 'createForm'])->name('tenant.booking.create-form');
            Route::post('/confirm-form', [BookingController::class, 'confirmForm'])->name('tenant.booking.confirm-form');
            Route::post('/store', [BookingController::class, 'store'])->name('tenant.booking.store');
            Route::get('/success/{booking_id}', [BookingController::class, 'success'])->name('tenant.booking.success');

            // Sub-Modul Reschedule Sesi Jadwal Bermain (H-3)
            Route::get('/form-reschedule/{detail_booking_id}', [RescheduleDetailBookingController::class, 'formInput'])->name('tenant.booking.form.reschedule');
            Route::post('/confirmation-reschedule', [RescheduleDetailBookingController::class, 'confirmation'])->name('tenant.booking.confirmation.reschedule');
            Route::post('/process-reschedule', [RescheduleDetailBookingController::class, 'process'])->name('tenant.booking.process.reschedule');

            // Sub-Modul Pembatalan Sesi Jadwal Bermain (Cancellation)
            Route::get('/form-cancelled/{detail_booking_id}', [CancelledDetailBookingController::class, 'formInput'])->name('tenant.booking.form.cancelled');
            Route::post('/confirmation-cancelled', [CancelledDetailBookingController::class, 'confirmation'])->name('tenant.booking.confirmation.cancelled');
            Route::post('/process-cancelled', [CancelledDetailBookingController::class, 'process'])->name('tenant.booking.process.cancelled');
        });

        Route::get('/notifications', [NotificationController::class, 'index'])->name('tenant.notifications.index');
        Route::get('/notifications/{id}/read', [NotificationController::class, 'read'])->name('tenant.notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('tenant.notifications.readAll');
    });
});
