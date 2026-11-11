<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\CourtController;
use App\Http\Controllers\CourtScheduleController;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Court owner area
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});

Route::resource('courts', CourtController::class)->except(['show', 'destroy']);
Route::patch('courts/{court}/toggle', [CourtController::class, 'toggle'])->name('courts.toggle');

Route::get('/book', [CourtScheduleController::class, 'index'])->name('book');

Route::middleware('auth')->group(function () {
    Route::get('/book/new', [BookingController::class, 'create'])->name('bookings.create');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{appointment}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{appointment}/payment', [BookingController::class, 'submitPayment'])->name('bookings.payment');
    Route::post('/bookings/{appointment}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
});

Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
Route::post('bookings/{appointment}/confirm', [AdminBookingController::class, 'confirm'])->name('bookings.confirm');
Route::post('bookings/{appointment}/cancel', [AdminBookingController::class, 'cancel'])->name('bookings.cancel');