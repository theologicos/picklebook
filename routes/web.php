<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\CourtController;
use App\Http\Controllers\CourtScheduleController;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;

use App\Http\Controllers\Admin\OpenPlayController as AdminOpenPlayController;

use App\Http\Controllers\OpenPlayController;

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

Route::get('open-play', [AdminOpenPlayController::class, 'index'])->name('open-play.index');
Route::get('open-play/create', [AdminOpenPlayController::class, 'create'])->name('open-play.create');
Route::post('open-play', [AdminOpenPlayController::class, 'store'])->name('open-play.store');
Route::get('open-play/{session}', [AdminOpenPlayController::class, 'show'])->name('open-play.show');
Route::post('open-play/{session}/start', [AdminOpenPlayController::class, 'start'])->name('open-play.start');
Route::post('open-play/{session}/close', [AdminOpenPlayController::class, 'close'])->name('open-play.close');
Route::post('open-play/{session}/cancel', [AdminOpenPlayController::class, 'cancel'])->name('open-play.cancel');
Route::post('open-play/{session}/games', [AdminOpenPlayController::class, 'startGame'])->name('open-play.games.start');
Route::post('open-play/games/{game}/finish', [AdminOpenPlayController::class, 'finishGame'])->name('open-play.games.finish');
Route::post('open-play/entries/{entry}/check-in', [AdminOpenPlayController::class, 'checkIn'])->name('open-play.entries.check-in');
Route::post('open-play/entries/{entry}/remove', [AdminOpenPlayController::class, 'remove'])->name('open-play.entries.remove');

Route::get('/open-play', [OpenPlayController::class, 'index'])->name('open-play.index');
Route::get('/open-play/{session}', [OpenPlayController::class, 'show'])->name('open-play.show');

Route::middleware('auth')->group(function () {
    Route::post('/open-play/{session}/join', [OpenPlayController::class, 'join'])->name('open-play.join');
    Route::post('/open-play/entries/{entry}/payment', [OpenPlayController::class, 'submitPayment'])->name('open-play.payment');
    Route::post('/open-play/entries/{entry}/queue', [OpenPlayController::class, 'enterQueue'])->name('open-play.queue');
    Route::post('/open-play/entries/{entry}/step-out', [OpenPlayController::class, 'stepOut'])->name('open-play.step-out');
    Route::post('/open-play/entries/{entry}/leave', [OpenPlayController::class, 'leave'])->name('open-play.leave');
});