<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_word_records', function (Blueprint $table) {
            $table->id();
            $table->string('player_id');
            $table->integer('word_count');
            $table->integer('highest_word_count')->default(0);
            $table->timestamps();

            // Index for faster lookups
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_word_records');
    }
}; 