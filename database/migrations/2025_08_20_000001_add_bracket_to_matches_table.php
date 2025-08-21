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
        Schema::table('matches', function (Blueprint $table) {
            $table->string('bracket')->default('winners')->after('round_no');
            $table->integer('match_number')->default(0)->after('bracket');
            // Rename round_no to round for consistency with our code
            $table->renameColumn('round_no', 'round');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->renameColumn('round', 'round_no');
            $table->dropColumn('match_number');
            $table->dropColumn('bracket');
        });
    }
};