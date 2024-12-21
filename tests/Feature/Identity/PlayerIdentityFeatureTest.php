<?php

namespace Tests\Feature\Identity;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\LongestWord;

class PlayerIdentityFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_identity_persists_across_sessions()
    {
        // First request - establish player identity
        $response1 = $this->withHeaders([
            'User-Agent' => 'Test Browser 1.0',
            'Accept-Language' => 'en-US'
        ])->postJson('/api/v1/play-session/submit-word', [
            'word' => 'persistence'
        ]);

        $response1->assertSuccessful();
        $playerId1 = $response1->json('player_id');
        
        // Second request - simulating new session with same browser fingerprint
        $response2 = $this->withHeaders([
            'User-Agent' => 'Test Browser 1.0',
            'Accept-Language' => 'en-US'
        ])->postJson('/api/v1/play-session/submit-word', [
            'word' => 'identity'
        ]);

        $response2->assertSuccessful();
        $playerId2 = $response2->json('player_id');
        
        // Verify both requests used the same player ID
        $this->assertEquals($playerId1, $playerId2);
    }

    public function test_concurrent_sessions_share_player_identity()
    {
        // First request
        $response1 = $this->withHeaders([
            'User-Agent' => 'Test Browser 1.0',
            'Accept-Language' => 'en-US'
        ])->postJson('/api/v1/play-session/submit-word', [
            'word' => 'concurrent'
        ]);

        $response1->assertSuccessful();
        $playerId1 = $response1->json('player_id');
        
        // Second request in parallel session with a longer word
        $response2 = $this->withHeaders([
            'User-Agent' => 'Test Browser 1.0',
            'Accept-Language' => 'en-US'
        ])->postJson('/api/v1/play-session/submit-word', [
            'word' => 'parallelized' // Longer word
        ]);

        $response2->assertSuccessful();
        $playerId2 = $response2->json('player_id');
        
        // Verify both submissions use same player ID
        $this->assertEquals($playerId1, $playerId2);

        // Verify only the longer word is stored
        $this->assertDatabaseMissing('longest_words', [
            'player_id' => $playerId1,
            'word' => 'concurrent'
        ]);
        $this->assertDatabaseHas('longest_words', [
            'player_id' => $playerId1,
            'word' => 'parallelized'
        ]);
    }

    public function test_different_browsers_get_different_identities()
    {
        // First request with Chrome
        $response1 = $this->withHeaders([
            'User-Agent' => 'Chrome/91.0',
            'Accept-Language' => 'en-US'
        ])->postJson('/api/v1/play-session/submit-word', [
            'word' => 'chrome'
        ]);

        $response1->assertSuccessful();
        $playerId1 = $response1->json('player_id');

        // Clear session to simulate different browser
        $this->refreshApplication();
        
        // Second request with Firefox
        $response2 = $this->withHeaders([
            'User-Agent' => 'Firefox/89.0',
            'Accept-Language' => 'en-US'
        ])->postJson('/api/v1/play-session/submit-word', [
            'word' => 'firefox'
        ]);

        $response2->assertSuccessful();
        $playerId2 = $response2->json('player_id');
        
        // Verify different browsers get different IDs
        $this->assertNotEquals($playerId1, $playerId2);
    }

    public function test_player_identity_maintained_with_ip_change()
    {
        // First request with original IP
        $response1 = $this->withHeaders([
            'User-Agent' => 'Test Browser 1.0',
            'Accept-Language' => 'en-US',
            'REMOTE_ADDR' => '192.168.1.1'
        ])->postJson('/api/v1/play-session/submit-word', [
            'word' => 'original'
        ]);

        $response1->assertSuccessful();
        $playerId1 = $response1->json('player_id');
        
        // Second request with different IP
        $response2 = $this->withHeaders([
            'User-Agent' => 'Test Browser 1.0',
            'Accept-Language' => 'en-US',
            'REMOTE_ADDR' => '192.168.1.2'
        ])->postJson('/api/v1/play-session/submit-word', [
            'word' => 'changed'
        ]);

        $response2->assertSuccessful();
        $playerId2 = $response2->json('player_id');
        
        // Verify player ID remains the same despite IP change
        $this->assertEquals($playerId1, $playerId2);
    }

    public function test_player_identity_handles_language_preference_changes()
    {
        // First request with English
        $response1 = $this->withHeaders([
            'User-Agent' => 'Test Browser 1.0',
            'Accept-Language' => 'en-US'
        ])->postJson('/api/v1/play-session/submit-word', [
            'word' => 'english'
        ]);

        $response1->assertSuccessful();
        $playerId1 = $response1->json('player_id');
        
        // Second request with Spanish
        $response2 = $this->withHeaders([
            'User-Agent' => 'Test Browser 1.0',
            'Accept-Language' => 'es-ES'
        ])->postJson('/api/v1/play-session/submit-word', [
            'word' => 'spanish'
        ]);

        $response2->assertSuccessful();
        $playerId2 = $response2->json('player_id');
        
        // Verify player ID remains the same despite language change
        $this->assertEquals($playerId1, $playerId2);
    }
} 