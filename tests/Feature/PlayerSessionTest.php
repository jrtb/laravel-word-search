<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\PlayerIdentityService;
use Carbon\Carbon;
use Mockery;

class PlayerSessionTest extends TestCase
{
    use RefreshDatabase;

    private $playerIdentityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->playerIdentityService = Mockery::mock(PlayerIdentityService::class);
        $this->app->instance(PlayerIdentityService::class, $this->playerIdentityService);
    }

    public function test_can_record_first_session()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->once()
            ->withAnyArgs()
            ->andReturn($playerId);

        Carbon::setTestNow('2024-03-19 10:00:00');

        $response = $this->postJson('/api/v1/session');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'current_streak' => 1,
                'highest_streak' => 1,
                'last_session_date' => '2024-03-19'
            ]);
    }

    public function test_multiple_sessions_same_day_maintains_streak()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->times(2)
            ->withAnyArgs()
            ->andReturn($playerId);

        Carbon::setTestNow('2024-03-19 10:00:00');

        // First session
        $this->postJson('/api/v1/session');

        // Second session same day
        $response = $this->postJson('/api/v1/session');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'current_streak' => 1,
                'highest_streak' => 1,
                'last_session_date' => '2024-03-19'
            ]);
    }

    public function test_consecutive_days_increase_streak()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->times(2)
            ->withAnyArgs()
            ->andReturn($playerId);

        // First day
        Carbon::setTestNow('2024-03-19 10:00:00');
        $this->postJson('/api/v1/session');

        // Next day
        Carbon::setTestNow('2024-03-20 10:00:00');
        $response = $this->postJson('/api/v1/session');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'current_streak' => 2,
                'highest_streak' => 2,
                'last_session_date' => '2024-03-20'
            ]);
    }

    public function test_missing_day_resets_streak()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->times(2)
            ->withAnyArgs()
            ->andReturn($playerId);

        // First day
        Carbon::setTestNow('2024-03-19 10:00:00');
        $this->postJson('/api/v1/session');

        // Skip a day and play again
        Carbon::setTestNow('2024-03-21 10:00:00');
        $response = $this->postJson('/api/v1/session');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'current_streak' => 1,
                'highest_streak' => 1,
                'last_session_date' => '2024-03-21'
            ]);
    }

    public function test_can_get_streak_info()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->times(2)
            ->withAnyArgs()
            ->andReturn($playerId);

        // Create a session
        Carbon::setTestNow('2024-03-19 10:00:00');
        $this->postJson('/api/v1/session');

        // Get streak info
        $response = $this->getJson('/api/v1/session/streak');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'current_streak' => 1,
                'highest_streak' => 1,
                'last_session_date' => '2024-03-19'
            ]);
    }

    public function test_maintains_highest_streak_after_break()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->times(4)
            ->withAnyArgs()
            ->andReturn($playerId);

        // Day 1
        Carbon::setTestNow('2024-03-19 10:00:00');
        $this->postJson('/api/v1/session');

        // Day 2
        Carbon::setTestNow('2024-03-20 10:00:00');
        $this->postJson('/api/v1/session');

        // Day 3
        Carbon::setTestNow('2024-03-21 10:00:00');
        $this->postJson('/api/v1/session');

        // Skip a day and play again
        Carbon::setTestNow('2024-03-23 10:00:00');
        $response = $this->postJson('/api/v1/session');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'current_streak' => 1,
                'highest_streak' => 3,
                'last_session_date' => '2024-03-23'
            ]);
    }

    public function test_complex_streak_sequence()
    {
        $playerId = 'player-123';
        $this->playerIdentityService
            ->shouldReceive('findOrGeneratePlayerId')
            ->times(10) // We'll make 10 requests in this test
            ->withAnyArgs()
            ->andReturn($playerId);

        // Day 1: March 1 - First session
        Carbon::setTestNow('2024-03-01 10:00:00');
        $response = $this->postJson('/api/v1/session');
        $response->assertStatus(200)
            ->assertJson([
                'current_streak' => 1,
                'highest_streak' => 1,
                'last_session_date' => '2024-03-01'
            ]);

        // Day 1: Second session same day - should maintain same streak
        Carbon::setTestNow('2024-03-01 15:00:00');
        $response = $this->postJson('/api/v1/session');
        $response->assertJson([
            'current_streak' => 1,
            'highest_streak' => 1,
            'last_session_date' => '2024-03-01'
        ]);

        // Day 2: March 2 - Consecutive day
        Carbon::setTestNow('2024-03-02 10:00:00');
        $response = $this->postJson('/api/v1/session');
        $response->assertJson([
            'current_streak' => 2,
            'highest_streak' => 2,
            'last_session_date' => '2024-03-02'
        ]);

        // Day 3: March 3 - Consecutive day
        Carbon::setTestNow('2024-03-03 10:00:00');
        $response = $this->postJson('/api/v1/session');
        $response->assertJson([
            'current_streak' => 3,
            'highest_streak' => 3,
            'last_session_date' => '2024-03-03'
        ]);

        // Skip March 4 and 5

        // Day 4: March 6 - Break in streak
        Carbon::setTestNow('2024-03-06 10:00:00');
        $response = $this->postJson('/api/v1/session');
        $response->assertJson([
            'current_streak' => 1,
            'highest_streak' => 3, // Should maintain highest streak
            'last_session_date' => '2024-03-06'
        ]);

        // Day 5: March 7 - Building new streak
        Carbon::setTestNow('2024-03-07 10:00:00');
        $response = $this->postJson('/api/v1/session');
        $response->assertJson([
            'current_streak' => 2,
            'highest_streak' => 3,
            'last_session_date' => '2024-03-07'
        ]);

        // Day 6: March 8 - Continuing new streak
        Carbon::setTestNow('2024-03-08 10:00:00');
        $response = $this->postJson('/api/v1/session');
        $response->assertJson([
            'current_streak' => 3,
            'highest_streak' => 3,
            'last_session_date' => '2024-03-08'
        ]);

        // Day 7: March 9 - New record streak
        Carbon::setTestNow('2024-03-09 10:00:00');
        $response = $this->postJson('/api/v1/session');
        $response->assertJson([
            'current_streak' => 4,
            'highest_streak' => 4,
            'last_session_date' => '2024-03-09'
        ]);

        // Skip a week

        // Day 8: March 16 - Verify streak breaks after long gap
        Carbon::setTestNow('2024-03-16 10:00:00');
        $response = $this->postJson('/api/v1/session');
        $response->assertJson([
            'current_streak' => 1,
            'highest_streak' => 4, // Should maintain all-time highest
            'last_session_date' => '2024-03-16'
        ]);

        // Verify final streak state
        $response = $this->getJson('/api/v1/session/streak');
        $response->assertJson([
            'current_streak' => 1,
            'highest_streak' => 4,
            'last_session_date' => '2024-03-16'
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(); // Clear mock time
        Mockery::close();
    }
} 