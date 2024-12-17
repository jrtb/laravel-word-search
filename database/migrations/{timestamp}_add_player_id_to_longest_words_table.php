<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('longest_words', function (Blueprint $table) {
            $table->string('player_id')->after('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('longest_words', function (Blueprint $table) {
            $table->dropColumn('player_id');
        });
    }
}; 