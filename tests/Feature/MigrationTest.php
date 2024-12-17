<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_longest_words_table_has_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('longest_words', [
                'id',
                'session_id',
                'player_id',
                'word',
                'created_at',
                'updated_at'
            ])
        );
    }

    public function test_session_id_column_is_nullable(): void
    {
        $columnType = Schema::getColumnType('longest_words', 'session_id');
        $this->assertEquals('varchar', $columnType);

        // Test nullable by creating record without session_id
        DB::table('longest_words')->insert([
            'player_id' => 'test_player',
            'word' => 'test',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->assertDatabaseHas('longest_words', [
            'player_id' => 'test_player',
            'word' => 'test'
        ]);
    }
} 