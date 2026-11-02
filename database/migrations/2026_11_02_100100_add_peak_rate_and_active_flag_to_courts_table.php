<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->string('description', 255)->nullable();
            // Price per hour during peak hours (see config/picklebook.php). Null means same as hourly_rate.
            $table->unsignedInteger('peak_hourly_rate')->nullable();
            // Inactive courts are hidden from clients but keep their booking history.
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn(['description', 'peak_hourly_rate', 'is_active']);
        });
    }
};
