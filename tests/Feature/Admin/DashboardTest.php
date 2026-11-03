<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-12 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_players_cannot_open_the_admin_area(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_the_owner_sees_todays_numbers(): void
    {
        $court = Court::factory()->create();
        $today = now()->setTime(10, 0);

        // 2 hours, paid, 600 -> collected
        Appointment::factory()->for($court)->at($today, 2)->create(['total_amount' => 600]);
        // unpaid cash booking: counts as a booking but is not collected yet
        Appointment::factory()->for($court)->at($today->copy()->addHours(3))->create(['total_amount' => 300, 'payment_status' => 'unpaid']);
        // cancelled: ignored
        Appointment::factory()->for($court)->at($today->copy()->addHours(5))->cancelled()->create(['total_amount' => 999]);
        // earlier this month, paid: only in the monthly figure
        Appointment::factory()->for($court)->at(now()->subDays(5)->setTime(10, 0))->create(['total_amount' => 400]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['bookings_today'] === 2
                && $stats['hours_today'] === 3
                && $stats['revenue_today'] === 600
                && $stats['revenue_month'] === 1000
                && $stats['utilization'] === 19) // 3 of 16 hours
            ->assertSee('₱1,000');
    }
}
