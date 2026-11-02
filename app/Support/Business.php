<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * The one business Picklebook is installed for.
 *
 * Values come from the "settings" table (edited by the owner in Admin >
 * Settings) and fall back to config/picklebook.php. The settings are loaded
 * once per request, the first time something asks for them.
 */
class Business
{
    /** Setting key => config path holding its default. */
    protected const DEFAULTS = [
        'business_name' => 'picklebook.name',
        'address' => 'picklebook.address',
        'contact_number' => 'picklebook.contact_number',
        'open_hour' => 'picklebook.open_hour',
        'close_hour' => 'picklebook.close_hour',
        'peak_hours' => 'picklebook.peak_hours',
        'max_duration_hours' => 'picklebook.max_duration_hours',
        'advance_booking_days' => 'picklebook.advance_booking_days',
        'payment_hold_minutes' => 'picklebook.payment_hold_minutes',
        'cancellation_hours_before' => 'picklebook.cancellation_hours_before',
        'allow_pay_at_court' => 'picklebook.allow_pay_at_court',
        'gcash_number' => 'picklebook.gcash.number',
        'gcash_account_name' => 'picklebook.gcash.account_name',
        'messenger_page' => 'picklebook.messenger.page',
        'default_locale' => 'picklebook.default_locale',
    ];

    /**
     * @param  array<string, string|null>|null  $stored  Preloaded settings (handy in tests); null loads them from the database.
     */
    public function __construct(protected ?array $stored = null)
    {
        //
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();

        $value = $this->stored[$key] ?? null;

        if ($value === null || $value === '') {
            $value = isset(self::DEFAULTS[$key]) ? config(self::DEFAULTS[$key]) : null;
        }

        return $value ?? $default;
    }

    /**
     * Save one or more settings. Arrays are stored as JSON, booleans as 1/0.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            $stored = match (true) {
                is_array($value) => json_encode(array_values($value)),
                is_bool($value) => $value ? '1' : '0',
                $value === null || $value === '' => null,
                default => (string) $value,
            };

            Setting::updateOrCreate(['key' => $key], ['value' => $stored]);
        }

        $this->refresh();
    }

    public function refresh(): void
    {
        $this->stored = null;
    }

    protected function load(): void
    {
        $this->stored ??= Setting::query()->pluck('value', 'key')->all();
    }

    // ---- Profile -----------------------------------------------------------

    public function name(): string
    {
        return (string) $this->get('business_name', 'Picklebook');
    }

    public function address(): ?string
    {
        return $this->get('address');
    }

    public function contactNumber(): ?string
    {
        return $this->get('contact_number');
    }

    public function defaultLocale(): string
    {
        $locale = (string) $this->get('default_locale', 'en');

        return in_array($locale, ['en', 'fil'], true) ? $locale : 'en';
    }

    // ---- Opening hours -----------------------------------------------------

    public function openHour(): int
    {
        return (int) $this->get('open_hour', 6);
    }

    public function closeHour(): int
    {
        return (int) $this->get('close_hour', 22);
    }

    /**
     * Every hour a booking can start at, e.g. [6, 7, ... 21].
     *
     * @return list<int>
     */
    public function bookableHours(): array
    {
        return range($this->openHour(), $this->closeHour() - 1);
    }

    /**
     * @return list<int>
     */
    public function peakHours(): array
    {
        $value = $this->get('peak_hours', []);

        if (is_string($value)) {
            $value = json_decode($value, true) ?: [];
        }

        return array_values(array_map('intval', (array) $value));
    }

    public function isPeak(int $hour): bool
    {
        return in_array($hour, $this->peakHours(), true);
    }

    // ---- Booking rules -----------------------------------------------------

    public function maxDuration(): int
    {
        return max(1, (int) $this->get('max_duration_hours', 4));
    }

    public function advanceDays(): int
    {
        return max(0, (int) $this->get('advance_booking_days', 14));
    }

    public function holdMinutes(): int
    {
        return max(1, (int) $this->get('payment_hold_minutes', 60));
    }

    public function cancellationHours(): int
    {
        return max(0, (int) $this->get('cancellation_hours_before', 6));
    }

    public function allowPayAtCourt(): bool
    {
        return filter_var($this->get('allow_pay_at_court', true), FILTER_VALIDATE_BOOL);
    }

    // ---- Payments and Messenger ---------------------------------------------

    public function gcashNumber(): ?string
    {
        return $this->get('gcash_number');
    }

    public function gcashAccountName(): ?string
    {
        return $this->get('gcash_account_name');
    }

    public function hasGcash(): bool
    {
        return filled($this->gcashNumber());
    }

    /**
     * The page handle people can message, whatever the owner pasted in
     * ("mypage", "@mypage", "facebook.com/mypage", "https://m.me/mypage").
     */
    public function messengerHandle(): ?string
    {
        $page = trim((string) $this->get('messenger_page'));

        if ($page === '') {
            return null;
        }

        $page = preg_replace('#^(https?://)?(www\.)?(m\.me|facebook\.com|fb\.com|fb\.me|messenger\.com/t)/#i', '', $page);
        $page = trim(explode('?', (string) $page)[0], "/@ \t\n\r");

        return $page === '' ? null : $page;
    }

    /**
     * Opens a Messenger chat with the business. The optional ref (a booking code)
     * is passed along so the page admin can see where the chat came from.
     */
    public function messengerUrl(?string $ref = null): ?string
    {
        $handle = $this->messengerHandle();

        if ($handle === null) {
            return null;
        }

        return 'https://m.me/'.$handle.($ref ? '?ref='.rawurlencode($ref) : '');
    }

    public function openingHoursLabel(): string
    {
        return Format::hour($this->openHour()).' – '.Format::hour($this->closeHour());
    }

    /**
     * First moment a given day can no longer be booked online.
     */
    public function lastBookableDate(): CarbonInterface
    {
        return now()->addDays($this->advanceDays())->endOfDay();
    }
}
