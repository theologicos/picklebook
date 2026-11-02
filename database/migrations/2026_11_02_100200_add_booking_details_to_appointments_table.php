<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Business rule for effective_datetime: a booking always starts on the hour
     * and lasts a whole number of hours. We store the start (effective_datetime),
     * the length (duration_hours) and the computed end (ends_at) so that checking
     * for a clash is one simple range query.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Short code players can quote on Messenger, e.g. PB-7K3QX2.
            $table->string('reference_code', 12)->nullable()->unique();
            $table->unsignedTinyInteger('duration_hours')->default(1);
            $table->dateTime('ends_at')->nullable();

            // pending | confirmed | cancelled
            $table->string('status', 20)->default('pending')->index();
            // Price in whole pesos, frozen at booking time so later rate changes don't rewrite history.
            $table->unsignedInteger('total_amount')->default(0);

            // gcash | cash
            $table->string('payment_method', 20)->default('gcash');
            // unpaid | for_verification | paid
            $table->string('payment_status', 20)->default('unpaid');
            // Reference number from the customer's GCash receipt. Unique so one payment can't pay for two bookings.
            $table->string('gcash_reference', 20)->nullable()->unique();

            $table->string('notes', 500)->nullable();
            $table->timestamp('updated_at')->nullable();

            // The availability check always filters by court and time range.
            $table->index(['court_id', 'effective_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(['reference_code']);
            $table->dropUnique(['gcash_reference']);
            $table->dropIndex(['status']);
            $table->dropIndex(['court_id', 'effective_datetime']);
            $table->dropColumn([
                'reference_code',
                'duration_hours',
                'ends_at',
                'status',
                'total_amount',
                'payment_method',
                'payment_status',
                'gcash_reference',
                'notes',
                'updated_at',
            ]);
        });
    }
};
