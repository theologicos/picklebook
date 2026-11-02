<?php

namespace Tests\Feature;

use App\Support\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_config_defaults(): void
    {
        $business = new Business();

        $this->assertSame('Picklebook', $business->name());
        $this->assertSame(6, $business->openHour());
        $this->assertSame(22, $business->closeHour());
        $this->assertSame([17, 18, 19, 20, 21], $business->peakHours());
        $this->assertTrue($business->isPeak(18));
        $this->assertFalse($business->isPeak(9));
        $this->assertFalse($business->hasGcash());
    }

    public function test_saved_settings_override_defaults(): void
    {
        $business = new Business();
        $business->save([
            'business_name' => 'Sunrise Pickle Club',
            'open_hour' => 8,
            'peak_hours' => [18, 19],
            'allow_pay_at_court' => false,
            'gcash_number' => '09171234567',
        ]);

        $this->assertSame('Sunrise Pickle Club', $business->name());
        $this->assertSame(8, $business->openHour());
        $this->assertSame([18, 19], $business->peakHours());
        $this->assertFalse($business->allowPayAtCourt());
        $this->assertTrue($business->hasGcash());
        $this->assertSame(range(8, 21), $business->bookableHours());
    }

    public function test_clearing_a_setting_returns_to_the_default(): void
    {
        $business = new Business();
        $business->save(['business_name' => 'Custom']);
        $business->save(['business_name' => '']);

        $this->assertSame('Picklebook', $business->name());
    }

    public function test_messenger_handle_accepts_whatever_the_owner_pastes(): void
    {
        foreach ([
            'mypage',
            '@mypage',
            'facebook.com/mypage',
            'https://www.facebook.com/mypage/',
            'https://m.me/mypage?ref=x',
        ] as $input) {
            $business = new Business(['messenger_page' => $input]);

            $this->assertSame('mypage', $business->messengerHandle(), $input);
        }
    }

    public function test_messenger_url_includes_the_booking_reference(): void
    {
        $business = new Business(['messenger_page' => 'mypage']);

        $this->assertSame('https://m.me/mypage', $business->messengerUrl());
        $this->assertSame('https://m.me/mypage?ref=PB-ABC123', $business->messengerUrl('PB-ABC123'));
        $this->assertNull((new Business([]))->messengerUrl());
    }
}
