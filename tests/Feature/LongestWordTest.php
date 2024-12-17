<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LongestWordTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_and_update_session_id(): void
    {
        // Mock a player identity
        $playerId = 'test_player_' . time();
        $sessionId = 'test_session_' . time();
        
        // Submit a word with session
        $response = $this->postJson('/api/v1/longest-word', [
            'word' => 'testing',
            'player_id' => $playerId,
            'session_id' => $sessionId
        ]);

        $response->assertStatus(200);
        
        // Verify session was stored
        $this->assertDatabaseHas('longest_words', [
            'player_id' => $playerId,
            'session_id' => $sessionId,
            'word' => 'testing'
        ]);

        // Update with new session
        $newSessionId = 'new_session_' . time();
        $response = $this->postJson('/api/v1/longest-word', [
            'word' => 'testing',
            'player_id' => $playerId,
            'session_id' => $newSessionId
        ]);

        // Verify session was updated
        $this->assertDatabaseHas('longest_words', [
            'player_id' => $playerId,
            'session_id' => $newSessionId,
            'word' => 'testing'
        ]);
    }

    public function test_api_returns_json_response(): void
    {
        $response = $this->getJson('/api/v1/longest-word');
        
        $response
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure([
                'success',
                'longest_word',
                'length',
                'player_id'
            ]);
    }

    public function test_top_words_returns_json_response(): void
    {
        $response = $this->getJson('/api/v1/longest-word/top');
        
        $response
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure([
                'success',
                'words' => [
                    '*' => [
                        'word',
                        'player_id',
                        'length',
                        'submitted_at'
                    ]
                ]
            ]);
    }
} 