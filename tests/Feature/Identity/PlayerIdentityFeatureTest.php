<?php

namespace Tests\Feature\Identity;

use App\Models\LongestWord;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

class PlayerIdentityFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function player_identity_persists_across_sessions()
    {
        // First request - simulating initial session
        $response1 = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Test Browser',
            'Accept-Language' => 'en-US',
            'REMOTE_ADDR' => '192.168.1.1',
        ])->postJson('/api/v1/longest-word', [
            'word' => 'persistence'
        ]);

        $response1->assertSuccessful();
        $playerId1 = $response1->json('player_id');
        
        // Second request - simulating new session with same browser fingerprint
        $response2 = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Test Browser',
            'Accept-Language' => 'en-US',
            'REMOTE_ADDR' => '192.168.1.1',
        ])->getJson('/api/v1/longest-word');

        $response2->assertSuccessful();
        $playerId2 = $response2->json('player_id');

        // Verify same player ID is returned
        $this->assertEquals($playerId1, $playerId2);
        $this->assertDatabaseHas('longest_words', [
            'player_id' => $playerId1,
            'word' => 'persistence'
        ]);
    }

    #[Test]
    public function concurrent_sessions_share_player_identity()
    {
        // Submit a word from first "browser"
        $response1 = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Test Browser',
            'Accept-Language' => 'en-US',
            'REMOTE_ADDR' => '192.168.1.1',
        ])->postJson('/api/v1/longest-word', [
            'word' => 'concurrent'
        ]);

        $playerId1 = $response1->json('player_id');

        // Submit another word from "same browser" in different tab
        $response2 = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Test Browser',
            'Accept-Language' => 'en-US',
            'REMOTE_ADDR' => '192.168.1.1',
        ])->postJson('/api/v1/longest-word', [
            'word' => 'simultaneous'
        ]);

        $playerId2 = $response2->json('player_id');

        // Verify both submissions use same player ID
        $this->assertEquals($playerId1, $playerId2);
        $this->assertDatabaseHas('longest_words', [
            'player_id' => $playerId1,
            'word' => 'concurrent'
        ]);
        $this->assertDatabaseHas('longest_words', [
            'player_id' => $playerId1,
            'word' => 'simultaneous'
        ]);
    }

    #[Test]
    public function different_browsers_get_different_identities()
    {
        // First browser (Chrome on Windows)
        $response1 = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Charset' => 'utf-8',
        ])->postJson('/api/v1/longest-word', [
            'word' => 'chrome'
        ]);

        $response1->assertSuccessful();
        $playerId1 = $response1->json('player_id');

        // Clear session to simulate different browser
        $this->refreshApplication();

        // Second browser (Firefox on Mac)
        $response2 = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15) Firefox/89.0',
            'Accept-Language' => 'fr-FR,fr;q=0.9',
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Charset' => 'iso-8859-1',
        ])->postJson('/api/v1/longest-word', [
            'word' => 'firefox'
        ]);

        $response2->assertSuccessful();
        $playerId2 = $response2->json('player_id');

        // Verify different player IDs
        $this->assertNotEquals($playerId1, $playerId2);
        $this->assertDatabaseHas('longest_words', [
            'player_id' => $playerId1,
            'word' => 'chrome'
        ]);
        $this->assertDatabaseHas('longest_words', [
            'player_id' => $playerId2,
            'word' => 'firefox'
        ]);
    }

    #[Test]
    public function player_identity_maintained_with_ip_change()
    {
        // Initial request with first IP
        $response1 = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Test Browser',
            'Accept-Language' => 'en-US',
            'REMOTE_ADDR' => '192.168.1.1',
        ])->postJson('/api/v1/longest-word', [
            'word' => 'network'
        ]);

        // Second request with different IP but same browser fingerprint
        $response2 = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Test Browser',
            'Accept-Language' => 'en-US',
            'REMOTE_ADDR' => '192.168.1.2', // Different IP
        ])->getJson('/api/v1/longest-word');

        $playerId1 = $response1->json('player_id');
        $playerId2 = $response2->json('player_id');

        // Player should be recognized despite IP change
        $this->assertEquals($playerId1, $playerId2);
    }

    #[Test]
    public function player_identity_handles_language_preference_changes()
    {
        // Initial request with first language preference
        $response1 = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Test Browser',
            'Accept-Language' => 'en-US',
            'REMOTE_ADDR' => '192.168.1.1',
        ])->postJson('/api/v1/longest-word', [
            'word' => 'language'
        ]);

        // Second request with different language preference
        $response2 = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Test Browser',
            'Accept-Language' => 'fr-FR', // Different language
            'REMOTE_ADDR' => '192.168.1.1',
        ])->getJson('/api/v1/longest-word');

        $playerId1 = $response1->json('player_id');
        $playerId2 = $response2->json('player_id');

        // Player should be recognized despite language change
        $this->assertEquals($playerId1, $playerId2);
    }
} 