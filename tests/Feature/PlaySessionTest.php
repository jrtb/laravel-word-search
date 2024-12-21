<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\PlayerIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class PlaySessionTest extends TestCase
{
    use RefreshDatabase;

    private $playerIdentityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->playerIdentityService = Mockery::mock(PlayerIdentityService::class);
        $this->app->instance(PlayerIdentityService::class, $this->playerIdentityService);
    }

    public function test_can_get_current_session_with_longest_word()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->withAnyArgs()
            ->andReturn($playerId);

        // Create a longest word record for the player
        \App\Models\LongestWord::create([
            'word' => 'EXTRAORDINARY',
            'session_id' => 'session-1',
            'player_id' => $playerId
        ]);

        $response = $this->getJson('/api/v1/play-session/current');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'longest_word' => 'EXTRAORDINARY',
                'longest_word_length' => 13
            ])
            ->assertJsonStructure([
                'success',
                'session_id',
                'omnigram',
                'started_at',
                'time_remaining',
                'words',
                'longest_word',
                'longest_word_length'
            ]);
    }

    public function test_current_session_with_no_longest_word()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->withAnyArgs()
            ->andReturn($playerId);

        $response = $this->getJson('/api/v1/play-session/current');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'longest_word' => '',
                'longest_word_length' => 0
            ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
} 