<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use App\Models\LongestWord;
use App\Services\PlayerIdentityService;
use Mockery;
use Illuminate\Http\Request;

class LongestWordApiTest extends TestCase
{
    use RefreshDatabase;

    private $playerIdentityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->playerIdentityService = Mockery::mock(PlayerIdentityService::class);
        $this->app->instance(PlayerIdentityService::class, $this->playerIdentityService);
    }

    public function test_can_submit_longest_word()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->withAnyArgs()
            ->andReturn($playerId);

        $response = $this->withSession(['_id' => 'session-1'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'extraordinary'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_longest' => true,
                'submitted_word' => 'extraordinary'
            ]);

        $this->assertDatabaseHas('longest_words', [
            'word' => 'extraordinary',
            'player_id' => $playerId
        ]);
    }

    public function test_maintains_player_id_across_sessions()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->twice()
            ->withAnyArgs()
            ->andReturn($playerId);

        // First session
        $response = $this->withSession(['_id' => 'session-1'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'extraordinary'
            ]);

        $response->assertStatus(200);

        // Second session
        $response = $this->withSession(['_id' => 'session-2'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'supercalifragilistic'
            ]);

        $response->assertStatus(200);

        // Both words should be associated with the same player
        $this->assertDatabaseHas('longest_words', [
            'word' => 'supercalifragilistic',
            'player_id' => $playerId
        ]);
    }

    public function test_maintains_separate_records_for_different_players()
    {
        $firstPlayerId = 'player-123';
        $secondPlayerId = 'player-456';

        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->withAnyArgs()
            ->andReturn($firstPlayerId);

        // First player submits a word
        $response = $this->withSession(['_id' => 'session-1'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'supercalifragilistic'
            ]);

        $response->assertStatus(200);

        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->withAnyArgs()
            ->andReturn($secondPlayerId);

        // Second player submits a word
        $response = $this->withSession(['_id' => 'session-2'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'short'
            ]);

        $response->assertStatus(200);

        // Verify each player has their own word
        $this->assertDatabaseHas('longest_words', [
            'word' => 'supercalifragilistic',
            'player_id' => $firstPlayerId
        ]);

        $this->assertDatabaseHas('longest_words', [
            'word' => 'short',
            'player_id' => $secondPlayerId
        ]);
    }

    public function test_can_get_longest_word()
    {
        $playerId = 'player-123';

        // Create a word first
        LongestWord::create([
            'word' => 'extraordinary',
            'session_id' => 'session-1',
            'player_id' => $playerId
        ]);

        // Set up mock expectation for when we get the word
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->withAnyArgs()
            ->andReturn($playerId);

        // Now get the word
        $response = $this->withSession(['_id' => 'session-1'])
            ->getJson('/api/v1/longest-word');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'longest_word' => 'extraordinary',
                'length' => 13,
                'player_id' => $playerId
            ]);
    }

    public function test_can_get_top_words()
    {
        // Create several words with different lengths
        LongestWord::create([
            'word' => 'short',
            'session_id' => 'session-1',
            'player_id' => 'player-1'
        ]);

        LongestWord::create([
            'word' => 'extraordinary',
            'session_id' => 'session-2',
            'player_id' => 'player-2'
        ]);

        LongestWord::create([
            'word' => 'supercalifragilistic',
            'session_id' => 'session-3',
            'player_id' => 'player-3'
        ]);

        $response = $this->getJson('/api/v1/longest-word/top');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(3, 'words')
            ->assertJsonPath('words.0.word', 'supercalifragilistic')
            ->assertJsonPath('words.0.length', 20)
            ->assertJsonPath('words.1.word', 'extraordinary')
            ->assertJsonPath('words.1.length', 13)
            ->assertJsonPath('words.2.word', 'short')
            ->assertJsonPath('words.2.length', 5);
    }

    public function test_invalid_word_submission()
    {
        $response = $this->withSession(['_id' => 'session-1'])
            ->postJson('/api/v1/longest-word', [
                'word' => '' // Empty word
            ]);

        $response->assertStatus(422);
    }

    public function test_fingerprint_based_player_identity()
    {
        // Remove the mock to test actual fingerprint generation
        $this->app->forgetInstance(PlayerIdentityService::class);

        // Create a new instance to test directly
        $playerIdentityService = new PlayerIdentityService();

        // First request with specific headers
        $headers1 = [
            'User-Agent' => 'Test Browser 1.0',
            'Accept-Language' => 'en-US',
        ];
        $server1 = ['REMOTE_ADDR' => '192.168.1.1'];
        
        $request1 = Request::create('/api/v1/longest-word', 'POST', [], [], [], 
            array_merge($server1, [
                'HTTP_USER_AGENT' => $headers1['User-Agent'],
                'HTTP_ACCEPT_LANGUAGE' => $headers1['Accept-Language']
            ])
        );
        
        $fingerprint1 = $playerIdentityService->generateFingerprint($request1);
        
        $response1 = $this->withHeaders($headers1)
            ->withServerVariables($server1)
            ->withSession(['_id' => 'session-1'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'extraordinary'
            ]);

        $response1->assertStatus(200);

        // Get the first word and its player ID
        $firstWord = LongestWord::where('word', 'extraordinary')->first();
        $this->assertNotNull($firstWord, 'First word should be saved');
        $playerId1 = $firstWord->player_id;

        // Second request with same headers but different session
        $response2 = $this->withHeaders($headers1)
            ->withServerVariables($server1)
            ->withSession(['_id' => 'session-2'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'supercalifragilistic'
            ]);

        $response2->assertStatus(200);
        
        // Get the second word and its player ID
        $secondWord = LongestWord::where('word', 'supercalifragilistic')->first();
        $this->assertNotNull($secondWord, 'Second word should be saved');
        $playerId2 = $secondWord->player_id;

        // Verify both requests got the same player ID due to same fingerprint
        $this->assertEquals($playerId1, $playerId2, 'Same fingerprint should give same player ID');

        // Third request with different headers should get different player ID
        $headers2 = [
            'User-Agent' => 'Different Browser 2.0',
            'Accept-Language' => 'fr-FR',
        ];
        $server2 = ['REMOTE_ADDR' => '192.168.1.2'];

        $request2 = Request::create('/api/v1/longest-word', 'POST', [], [], [], 
            array_merge($server2, [
                'HTTP_USER_AGENT' => $headers2['User-Agent'],
                'HTTP_ACCEPT_LANGUAGE' => $headers2['Accept-Language']
            ])
        );
        
        $fingerprint2 = $playerIdentityService->generateFingerprint($request2);

        $this->assertNotEquals($fingerprint1, $fingerprint2, 'Fingerprints should be different');

        $response3 = $this->withHeaders($headers2)
            ->withServerVariables($server2)
            ->withSession(['_id' => 'session-3'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'short'
            ]);

        $response3->assertStatus(200);

        // Get the third word and its player ID
        $thirdWord = LongestWord::where('word', 'short')->first();
        $this->assertNotNull($thirdWord, 'Third word should be saved');
        $playerId3 = $thirdWord->player_id;

        // Verify different fingerprint got different player ID
        $this->assertNotEquals($playerId1, $playerId3, 'Player IDs should be different for different fingerprints');
    }
} 