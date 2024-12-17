<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('longest_words')) {
            Schema::create('longest_words', function (Blueprint $table) {
                $table->id();
                $table->string('word');
                $table->string('session_id')->nullable(); // Full session ID for exact matching
                $table->string('player_id')->nullable(); // First 8 chars of session_id for display
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('longest_words');
    }
}; 