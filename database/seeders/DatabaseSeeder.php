<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\User;
use App\Support\Business;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Owner login: 09123456789 / password. Player login: 09123123123 / password.
     */
    public function run(): void
    {
        if (! User::query()->admins()->exists()) {
            User::factory()->admin()->create([
                'first_name' => 'Court',
                'last_name' => 'Owner',
                'mobile_number' => '09170000001',
                'password_hash' => 'password',
            ]);
        }

        if (! User::query()->where('mobile_number', '09170000002')->exists()) {
            User::factory()->create([
                'first_name' => 'Demo',
                'last_name' => 'Player',
                'mobile_number' => '09170000002',
                'password_hash' => 'password',
            ]);
        }

        foreach (['Court 1', 'Court 2', 'Court 3'] as $name) {
            Court::firstOrCreate(['name' => $name], [
                'description' => 'Outdoor court',
                'hourly_rate' => 300,
                'peak_hourly_rate' => 400,
                'is_active' => true,
            ]);
        }

        $business = app(Business::class);

        if (! $business->hasGcash()) {
            $business->save([
                'business_name' => 'Picklebook Demo Courts',
                'gcash_number' => '09171234567',
                'gcash_account_name' => 'Picklebook Demo',
            ]);
        }
    }
}