<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'mobile_number' => '0917 123 4567',
            'password' => 'secret-pass',
        ], $overrides);
    }

    public function test_the_registration_page_renders(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create your account');
    }

    public function test_a_player_can_register_and_is_logged_in(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('home'));

        $user = User::firstWhere('mobile_number', '09171234567');
        $this->assertNotNull($user);
        $this->assertSame('Juan', $user->first_name);
        $this->assertSame(UserRole::Client, $user->fresh()->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_password_is_stored_hashed(): void
    {
        $this->post('/register', $this->payload());

        $user = User::firstWhere('mobile_number', '09171234567');
        $this->assertNotSame('secret-pass', $user->password_hash);
        $this->assertTrue(password_verify('secret-pass', $user->password_hash));
    }

    public function test_nobody_can_register_themselves_as_admin(): void
    {
        $this->post('/register', $this->payload(['role' => 'admin']));

        $this->assertFalse(User::firstWhere('mobile_number', '09171234567')->fresh()->isAdmin());
    }

    public function test_a_mobile_number_can_only_be_used_once_in_any_format(): void
    {
        User::factory()->create(['mobile_number' => '09171234567']);

        $this->post('/register', $this->payload(['mobile_number' => '+639171234567']))
            ->assertSessionHasErrors('mobile_number');

        $this->assertSame(1, User::count());
    }

    public function test_the_fields_are_validated(): void
    {
        $this->post('/register', [])->assertSessionHasErrors(['first_name', 'last_name', 'mobile_number', 'password']);
        $this->post('/register', $this->payload(['password' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['mobile_number' => '12345']))->assertSessionHasErrors('mobile_number');
    }
}
