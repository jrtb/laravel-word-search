<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('player_id');
            $table->date('session_date');
            $table->integer('current_streak')->default(1);
            $table->integer('highest_streak')->default(1);
            $table->timestamps();

            // Index for faster lookups
            $table->index(['player_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_sessions');
    }
}; 