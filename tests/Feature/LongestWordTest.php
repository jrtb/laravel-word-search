<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LongestWordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_store_and_update_session_id(): void
    {
        // First request - establish player identity with a longer word
        $headers = [
            'User-Agent' => 'Test Browser 1.0',
            'Accept-Language' => 'en-US'
        ];
        
        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/longest-word', [
                'word' => 'extraordinary'
            ]);

        $response->assertStatus(200);
        
        // Get the player_id from the response
        $firstRecord = \App\Models\LongestWord::first();
        $playerId = $firstRecord->player_id;
        $sessionId = $firstRecord->session_id;
        
        // Verify session was stored
        $this->assertDatabaseHas('longest_words', [
            'player_id' => $playerId,
            'word' => 'extraordinary'
        ]);

        // Second request - should not store shorter word
        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/longest-word', [
                'word' => 'testing'
            ]);

        $response->assertStatus(200);
        
        // Verify:
        // 1. Player ID remains the same (identity preserved)
        // 2. Session handling is working (session ID exists)
        // 3. Word remains the same (shorter word not stored)
        $updatedRecord = \App\Models\LongestWord::first();
        $this->assertEquals($playerId, $updatedRecord->player_id);
        $this->assertNotNull($updatedRecord->session_id);
        $this->assertEquals('extraordinary', $updatedRecord->word);

        // Verify shorter word was not stored
        $this->assertDatabaseMissing('longest_words', [
            'player_id' => $playerId,
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
        $response = $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->get('/longest-word/top');
        
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