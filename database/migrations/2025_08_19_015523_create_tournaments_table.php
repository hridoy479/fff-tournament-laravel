<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('banner')->nullable();
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->decimal('entry_fee', 10, 2)->default(0);
            $table->decimal('prize_pool', 10, 2)->default(0);
            $table->integer('max_players')->default(0);
            $table->enum('format', ['single_elimination', 'double_elimination'])->default('single_elimination');
            $table->enum('status', ['draft', 'upcoming', 'registration_open', 'registration_closed', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->timestamp('reg_starts_at')->nullable();
            $table->timestamp('reg_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
