<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Mobile number');
    }

    public function test_a_client_can_log_in_with_their_mobile_number(): void
    {
        $user = User::factory()->create(['mobile_number' => '09171234567']);

        $this->post('/login', ['mobile_number' => '09171234567', 'password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_any_common_number_format_logs_into_the_same_account(): void
    {
        $user = User::factory()->create(['mobile_number' => '09171234567']);

        $this->post('/login', ['mobile_number' => '+63 917 123 4567', 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_owner_lands_on_the_admin_area(): void
    {
        User::factory()->admin()->create(['mobile_number' => '09181234567']);

        $this->post('/login', ['mobile_number' => '09181234567', 'password' => 'password'])
            ->assertRedirect('/admin');
    }

    public function test_players_return_to_the_page_they_were_trying_to_reach(): void
    {
        Route::middleware(['web', 'auth'])->get('/_private', fn () => 'private');
        User::factory()->create(['mobile_number' => '09171234567']);

        $this->get('/_private')->assertRedirect(route('login'));
        $this->post('/login', ['mobile_number' => '09171234567', 'password' => 'password'])
            ->assertRedirect('/_private');
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        User::factory()->create(['mobile_number' => '09171234567']);

        $this->from('/login')
            ->post('/login', ['mobile_number' => '09171234567', 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('mobile_number');

        $this->assertGuest();
    }

    public function test_an_invalid_mobile_number_is_rejected(): void
    {
        $this->post('/login', ['mobile_number' => '12345', 'password' => 'password'])
            ->assertSessionHasErrors('mobile_number');
    }

    public function test_repeated_failures_are_throttled(): void
    {
        User::factory()->create(['mobile_number' => '09171234567']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['mobile_number' => '09171234567', 'password' => 'nope']);
        }

        $this->post('/login', ['mobile_number' => '09171234567', 'password' => 'password'])
            ->assertStatus(429);
    }

    public function test_logged_in_users_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_logged_in_users_are_redirected_away_from_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect();
    }
}
