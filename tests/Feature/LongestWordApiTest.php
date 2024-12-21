<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use App\Models\LongestWord;
use App\Services\PlayerIdentityService;
use Mockery;
use Illuminate\Http\Request;
use Carbon\Carbon;

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
                'submitted_word' => 'extraordinary',
                'player_id' => $playerId
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

        // First session - longer word
        $response = $this->withSession(['_id' => 'session-1'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'extraordinary'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_longest' => true
            ]);

        // Second session - shorter word (should not be stored)
        $response = $this->withSession(['_id' => 'session-2'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'short'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_longest' => false
            ]);

        // Verify the longer word is stored
        $this->assertDatabaseHas('longest_words', [
            'word' => 'extraordinary',
            'player_id' => $playerId
        ]);

        // Verify the shorter word was not stored
        $this->assertDatabaseMissing('longest_words', [
            'word' => 'short',
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
                'word' => 'extraordinary'
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
            'word' => 'extraordinary',
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

        $response = $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->get('/longest-word/top');

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

    public function test_reuses_session_within_24_hours()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->twice()
            ->withAnyArgs()
            ->andReturn($playerId);

        // First submission at current time
        $now = Carbon::create(2024, 3, 20, 12, 0, 0);
        Carbon::setTestNow($now);

        $response = $this->withSession(['_id' => 'session-1'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'extraordinary'
            ]);

        $response->assertStatus(200);
        $firstSessionId = LongestWord::first()->session_id;

        // Second submission 23 hours later (should reuse session)
        Carbon::setTestNow($now->copy()->addHours(23));

        $response = $this->withSession(['_id' => 'session-2'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'supercalifragilistic'
            ]);

        $response->assertStatus(200);
        $secondSessionId = LongestWord::latest()->first()->session_id;

        // Verify same session was used
        $this->assertEquals($firstSessionId, $secondSessionId);
    }

    public function test_creates_new_session_after_24_hours()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->twice()
            ->withAnyArgs()
            ->andReturn($playerId);

        // First submission at current time
        $now = Carbon::create(2024, 3, 20, 12, 0, 0);
        Carbon::setTestNow($now);

        $response = $this->withSession(['_id' => 'session-1'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'extraordinary'
            ]);

        $response->assertStatus(200);
        $firstSessionId = LongestWord::first()->session_id;

        // Second submission 25 hours later (should create new session)
        Carbon::setTestNow($now->copy()->addHours(25));

        $response = $this->withSession(['_id' => 'session-2'])
            ->postJson('/api/v1/longest-word', [
                'word' => 'supercalifragilistic'
            ]);

        $response->assertStatus(200);
        $secondSessionId = LongestWord::latest()->first()->session_id;

        // Verify different sessions were used
        $this->assertNotEquals($firstSessionId, $secondSessionId);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        Carbon::setTestNow(); // Reset the mock time
    }
} 