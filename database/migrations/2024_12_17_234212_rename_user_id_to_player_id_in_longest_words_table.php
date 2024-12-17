<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('longest_words', function (Blueprint $table) {
            $table->renameColumn('user_id', 'player_id');
            $table->string('player_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('longest_words', function (Blueprint $table) {
            $table->renameColumn('player_id', 'user_id');
            $table->string('user_id')->nullable()->change();
        });
    }
}; 