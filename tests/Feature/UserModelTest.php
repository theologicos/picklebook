<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_are_the_default_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Client, $user->fresh()->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_admin_state_creates_an_admin(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->isAdmin());
    }

    public function test_mobile_numbers_are_normalized_when_assigned(): void
    {
        $user = User::factory()->create(['mobile_number' => '+63 917 123 4567']);

        $this->assertSame('09171234567', $user->fresh()->mobile_number);
    }

    public function test_full_name_falls_back_to_mobile_number(): void
    {
        $named = User::factory()->make(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $unnamed = User::factory()->make(['first_name' => null, 'last_name' => null, 'mobile_number' => '09171234567']);

        $this->assertSame('Juan Dela Cruz', $named->full_name);
        $this->assertSame('09171234567', $unnamed->full_name);
    }

    public function test_users_can_authenticate_with_password_hash_column(): void
    {
        User::factory()->create(['mobile_number' => '09171234567']);

        $this->assertTrue(Auth::attempt(['mobile_number' => '09171234567', 'password' => 'password']));
        Auth::logout();
        $this->assertFalse(Auth::attempt(['mobile_number' => '09171234567', 'password' => 'wrong']));
    }
}
