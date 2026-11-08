<?php

use App\Services\BookingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire-unpaid', function (BookingService $bookings) {
    $released = $bookings->expireUnpaid();
    $this->info("Released {$released} unpaid booking/s.");
})->purpose('Cancel unpaid GCash bookings whose payment hold has ran out');

Schedule::command('bookings:expire-unpaid')->everyMinute();