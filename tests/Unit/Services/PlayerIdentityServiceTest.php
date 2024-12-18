<?php

namespace Tests\Unit\Services;

use App\Models\LongestWord;
use App\Services\PlayerIdentityService;
use Illuminate\Http\Request;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PlayerIdentityServiceTest extends TestCase
{
    private PlayerIdentityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlayerIdentityService();
    }

    #[Test]
    public function it_generates_consistent_fingerprint_for_same_request_data()
    {
        $request1 = new Request();
        $request1->server->set('REMOTE_ADDR', '192.168.1.1');
        $request1->headers->set('User-Agent', 'Mozilla/5.0');
        $request1->headers->set('Accept-Language', 'en-US');

        $request2 = new Request();
        $request2->server->set('REMOTE_ADDR', '192.168.1.1');
        $request2->headers->set('User-Agent', 'Mozilla/5.0');
        $request2->headers->set('Accept-Language', 'en-US');

        $fingerprint1 = $this->service->generateFingerprint($request1);
        $fingerprint2 = $this->service->generateFingerprint($request2);

        $this->assertEquals($fingerprint1, $fingerprint2);
    }

    #[Test]
    public function it_generates_different_fingerprints_for_different_request_data()
    {
        // First request with its own session
        $request1 = new Request();
        $request1->headers->set('User-Agent', 'Mozilla/5.0');
        $request1->headers->set('Accept-Language', 'en-US');
        $request1->headers->set('Accept-Encoding', 'gzip, deflate');
        $request1->headers->set('Accept-Charset', 'utf-8');
        $fingerprint1 = $this->service->generateFingerprint($request1);

        // Second request with different headers
        $request2 = new Request();
        $request2->headers->set('User-Agent', 'Chrome/5.0');
        $request2->headers->set('Accept-Language', 'fr-FR');
        $request2->headers->set('Accept-Encoding', 'br');
        $request2->headers->set('Accept-Charset', 'iso-8859-1');
        $fingerprint2 = $this->service->generateFingerprint($request2);

        // Fingerprints should be different
        $this->assertNotEquals($fingerprint1, $fingerprint2);
    }

    #[Test]
    public function it_handles_missing_headers_gracefully()
    {
        $request1 = new Request();
        $request1->server->set('REMOTE_ADDR', '192.168.1.1');
        // No User-Agent or Accept-Language

        $request2 = new Request();
        $request2->server->set('REMOTE_ADDR', '192.168.1.1');
        // No User-Agent or Accept-Language

        $fingerprint1 = $this->service->generateFingerprint($request1);
        $fingerprint2 = $this->service->generateFingerprint($request2);

        $this->assertEquals($fingerprint1, $fingerprint2);
        $this->assertNotEmpty($fingerprint1);
    }

    #[Test]
    public function it_returns_existing_valid_player_id()
    {
        $existingPlayerId = 'existing_player_123';
        LongestWord::factory()->create(['player_id' => $existingPlayerId]);

        $request = new Request();
        $playerId = $this->service->findOrGeneratePlayerId($request, $existingPlayerId);

        $this->assertEquals($existingPlayerId, $playerId);
    }

    #[Test]
    public function it_generates_new_player_id_for_invalid_existing_id()
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $invalidPlayerId = 'invalid_player_123';
        $playerId = $this->service->findOrGeneratePlayerId($request, $invalidPlayerId);

        $this->assertNotEquals($invalidPlayerId, $playerId);
        $this->assertEquals($this->service->generateFingerprint($request), $playerId);
    }

    #[Test]
    public function it_returns_existing_player_id_based_on_fingerprint()
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $fingerprint = $this->service->generateFingerprint($request);
        LongestWord::factory()->create(['player_id' => $fingerprint]);

        $playerId = $this->service->findOrGeneratePlayerId($request);

        $this->assertEquals($fingerprint, $playerId);
    }

    #[Test]
    public function it_generates_new_player_id_for_new_fingerprint()
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $fingerprint = $this->service->generateFingerprint($request);
        
        // Clear any existing records with this fingerprint
        LongestWord::where('player_id', $fingerprint)->delete();
        
        $playerId = $this->service->findOrGeneratePlayerId($request);

        $this->assertEquals($fingerprint, $playerId);
        $this->assertEquals(0, LongestWord::where('player_id', $playerId)->count());
    }

    #[Test]
    public function it_handles_special_characters_in_headers()
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (特殊文字)');
        $request->headers->set('Accept-Language', 'zh-CN,zh;q=0.9,en;q=0.8');

        $fingerprint = $this->service->generateFingerprint($request);
        
        $this->assertNotEmpty($fingerprint);
        $this->assertEquals(64, strlen($fingerprint)); // SHA-256 produces 64 character hex string
    }
} 