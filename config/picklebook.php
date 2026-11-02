<?php

/*
|--------------------------------------------------------------------------
| Picklebook defaults
|--------------------------------------------------------------------------
|
| These are the out-of-the-box values. The owner can override most of them
| from Admin > Settings, which stores them in the "settings" table.
|
*/

return [
    'name' => 'Picklebook',
    'address' => null,
    'contact_number' => null,

    // Opening hours (24h clock). The last bookable hour starts at close_hour - 1.
    'open_hour' => 6,
    'close_hour' => 22,
    'peak_hours' => [17, 18, 19, 20, 21],
    'reminder_hours_before' => 24,

    // Booking rules
    'max_duration_hours' => 4,
    'advance_booking_days' => 14,
    // How long an unpaid GCash booking holds its slot before it is released.
    'payment_hold_minutes' => 60,
    // Players can cancel on their own until this many hours before the start.
    'cancellation_hours_before' => 6,
    'allow_pay_at_court' => true,

    // Payments: customers send the money to this GCash account, then submit the reference number.
    'gcash' => [
        'number' => null,
        'account_name' => null,
    ],

    // Facebook page used for Messenger links, e.g. "mycourtpage" or "https://facebook.com/mycourtpage".
    'messenger' => [
        'page' => null,
    ],

    // Interface language for new visitors: "en" or "fil".
    'default_locale' => 'en',
];
