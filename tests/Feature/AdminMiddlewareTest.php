<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'admin'])->get('/_admin-probe', fn () => 'admin area');
    }

    public function test_admins_get_through(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/_admin-probe')
            ->assertOk()
            ->assertSee('admin area');
    }

    public function test_clients_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/_admin-probe')
            ->assertForbidden();
    }
}
