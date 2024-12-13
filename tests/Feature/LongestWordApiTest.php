<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use App\Models\LongestWord;

class LongestWordApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Session::start();
    }

    public function test_can_submit_longest_word()
    {
        $response = $this->withSession([])
            ->postJson('/api/v1/longest-word', [
                'word' => 'extraordinary'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_longest' => true,
                'submitted_word' => 'extraordinary'
            ]);

        // Verify the word was saved
        $this->assertDatabaseHas('longest_words', [
            'word' => 'extraordinary'
        ]);
    }

    public function test_can_get_longest_word()
    {
        // Create a test session
        $sessionId = 'test-session-' . time();
        Session::put('_id', $sessionId);

        // Create a word with the session ID
        LongestWord::create([
            'word' => 'extraordinary',
            'user_id' => $sessionId
        ]);

        // Make the request with the same session
        $response = $this->withSession(['_id' => $sessionId])
            ->getJson('/api/v1/longest-word');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'longest_word' => 'extraordinary',
                'length' => 13
            ]);
    }

    public function test_invalid_word_submission()
    {
        $response = $this->withSession([])
            ->postJson('/api/v1/longest-word', [
                'word' => '' // Empty word
            ]);

        $response->assertStatus(422);
    }

    public function test_rate_limiting()
    {
        // Make 61 requests (1 over the limit)
        for ($i = 0; $i < 61; $i++) {
            $response = $this->withSession([])
                ->getJson('/api/v1/longest-word');
        }

        // The last request should be rate limited
        $response->assertStatus(429);
    }
} 