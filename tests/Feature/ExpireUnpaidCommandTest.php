<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireUnpaidCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:30:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_expired_unpaid_gcash_bookings_are_cancelled(): void
    {
        $stale = Appointment::factory()->pending()->create(['created_at' => now()->subHours(2)]);
        $fresh = Appointment::factory()->pending()->create(['created_at' => now()->subMinutes(5)]);
        $paid = Appointment::factory()->create(['created_at' => now()->subHours(2)]);

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();

        $this->assertSame(AppointmentStatus::Cancelled, $stale->fresh()->status);
        $this->assertSame(AppointmentStatus::Pending, $fresh->fresh()->status);
        $this->assertSame(AppointmentStatus::Confirmed, $paid->fresh()->status);
    }
}