<?php

namespace Tests\Feature;

use App\Models\GameWordRecord;
use App\Services\PlayerIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class GameWordRecordTest extends TestCase
{
    use RefreshDatabase;

    protected $playerIdentityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->playerIdentityService = Mockery::mock(PlayerIdentityService::class);
        $this->app->instance(PlayerIdentityService::class, $this->playerIdentityService);
    }

    public function test_can_get_highest_word_count(): void
    {
        $playerId = 'test-player-1';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->andReturn($playerId);

        // Create a record
        GameWordRecord::create([
            'player_id' => $playerId,
            'word_count' => 10,
            'highest_word_count' => 15
        ]);

        $response = $this->getJson('/api/v1/game-words/highest');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'highest_word_count' => 15,
                'player_id' => $playerId
            ]);
    }

    public function test_returns_zero_for_new_player(): void
    {
        $playerId = 'new-player';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->andReturn($playerId);

        $response = $this->getJson('/api/v1/game-words/highest');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'highest_word_count' => 0,
                'player_id' => $playerId
            ]);
    }

    public function test_can_get_top_word_counts(): void
    {
        // Create some test records
        GameWordRecord::create([
            'player_id' => 'player-1',
            'word_count' => 10,
            'highest_word_count' => 25
        ]);

        GameWordRecord::create([
            'player_id' => 'player-2',
            'word_count' => 15,
            'highest_word_count' => 30
        ]);

        GameWordRecord::create([
            'player_id' => 'player-3',
            'word_count' => 20,
            'highest_word_count' => 20
        ]);

        $response = $this->getJson('/api/v1/game-words/top');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(3, 'records')
            ->assertJsonPath('records.0.highest_word_count', 30)
            ->assertJsonPath('records.1.highest_word_count', 25)
            ->assertJsonPath('records.2.highest_word_count', 20);
    }

    public function test_can_update_word_count(): void
    {
        $playerId = 'test-player-2';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->andReturn($playerId);

        $response = $this->postJson('/api/v1/game-words/update', [
            'word_count' => 20
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'word_count' => 20,
                'highest_word_count' => 20,
                'is_new_record' => true,
                'player_id' => $playerId
            ]);

        $this->assertDatabaseHas('game_word_records', [
            'player_id' => $playerId,
            'word_count' => 20,
            'highest_word_count' => 20
        ]);
    }

    public function test_updates_highest_word_count_when_exceeded(): void
    {
        $playerId = 'test-player-3';
        
        // Set up initial record
        GameWordRecord::create([
            'player_id' => $playerId,
            'word_count' => 10,
            'highest_word_count' => 15
        ]);

        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->andReturn($playerId);

        $response = $this->postJson('/api/v1/game-words/update', [
            'word_count' => 20
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'word_count' => 20,
                'highest_word_count' => 20,
                'is_new_record' => true,
                'player_id' => $playerId
            ]);

        $this->assertDatabaseHas('game_word_records', [
            'player_id' => $playerId,
            'word_count' => 20,
            'highest_word_count' => 20
        ]);
    }

    public function test_maintains_highest_word_count_when_not_exceeded(): void
    {
        $playerId = 'test-player-4';
        
        // Set up initial record
        GameWordRecord::create([
            'player_id' => $playerId,
            'word_count' => 10,
            'highest_word_count' => 25
        ]);

        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->andReturn($playerId);

        $response = $this->postJson('/api/v1/game-words/update', [
            'word_count' => 20
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'word_count' => 20,
                'highest_word_count' => 25,
                'is_new_record' => false,
                'player_id' => $playerId
            ]);

        $this->assertDatabaseHas('game_word_records', [
            'player_id' => $playerId,
            'word_count' => 20,
            'highest_word_count' => 25
        ]);
    }

    public function test_validates_word_count_input(): void
    {
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->never();

        $response = $this->postJson('/api/v1/game-words/update', []);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'word_count' => ['The word count field is required.']
                ]
            ]);
    }

    public function test_validates_word_count_is_non_negative(): void
    {
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->never();

        $response = $this->postJson('/api/v1/game-words/update', [
            'word_count' => -1
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'word_count' => ['The word count field must be at least 0.']
                ]
            ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
} 