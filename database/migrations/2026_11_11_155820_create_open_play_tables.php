
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('open_play_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->integer('max_players')->default(16);
            $table->integer('fee_per_player')->default(0);
            $table->string('skill_level')->default('all');
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('open_play_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_play_session_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('registered');
            $table->string('payment_method')->default('cash');
            $table->string('payment_status')->default('unpaid');
            $table->string('gcash_reference')->nullable();
            $table->integer('amount')->default(0);
            $table->dateTime('queued_at')->nullable();
            $table->integer('games_played')->default(0);
            $table->timestamps();

            $table->unique(['open_play_session_id', 'user_id']);
        });

        Schema::create('open_play_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_play_session_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('open_play_game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_play_game_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('open_play_entry_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unique(['open_play_game_id', 'open_play_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_game_players');
        Schema::dropIfExists('open_play_games');
        Schema::dropIfExists('open_play_entries');
        Schema::dropIfExists('open_play_sessions');
    }
};
