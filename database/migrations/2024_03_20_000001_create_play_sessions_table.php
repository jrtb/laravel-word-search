<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('player_id');
            $table->string('omnigram');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('player_id');
            $table->index(['player_id', 'started_at']);
        });

        Schema::create('play_session_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_session_id')->constrained()->onDelete('cascade');
            $table->string('word');
            $table->timestamps();

            $table->unique(['play_session_id', 'word']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_session_words');
        Schema::dropIfExists('play_sessions');
    }
}; 