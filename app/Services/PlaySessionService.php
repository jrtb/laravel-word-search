<?php

namespace App\Services;

use App\Models\PlaySession;
use App\Models\PlaySessionWord;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlaySessionService
{
    private OmnigramService $omnigramService;

    public function __construct(OmnigramService $omnigramService)
    {
        $this->omnigramService = $omnigramService;
    }

    public function getCurrentSession(string $playerId): array
    {
        $session = PlaySession::getCurrentSession($playerId);

        if (!$session) {
            $session = $this->startNewSession($playerId);
        }

        return [
            'session_id' => $session->id,
            'omnigram' => $session->omnigram,
            'score' => $session->score,
            'started_at' => $session->started_at->toIso8601String(),
            'words' => $session->words->map(fn(PlaySessionWord $word) => [
                'word' => $word->word,
                'points' => $word->points,
            ])->values(),
            'time_remaining' => $this->getTimeRemaining($session),
        ];
    }

    public function submitWord(string $playerId, string $word): array
    {
        $session = PlaySession::getCurrentSession($playerId);

        if (!$session) {
            $session = $this->startNewSession($playerId);
        }

        // Validate word against the current omnigram
        $points = $this->calculatePoints($word, $session->omnigram);

        try {
            $sessionWord = $session->addWord($word, $points);

            return [
                'success' => true,
                'word' => $sessionWord->word,
                'points' => $sessionWord->points,
                'total_score' => $session->score,
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getTopScores(int $limit = 10): Collection
    {
        return PlaySession::with('words')
            ->whereNotNull('ended_at')
            ->orderByDesc('score')
            ->limit($limit)
            ->get()
            ->map(fn(PlaySession $session) => [
                'player_id' => $session->player_id,
                'score' => $session->score,
                'word_count' => $session->words->count(),
                'date' => $session->started_at->toDateString(),
            ]);
    }

    private function startNewSession(string $playerId): PlaySession
    {
        $omnigram = $this->omnigramService->getRandomOmnigram();
        return PlaySession::startNewSession($playerId, $omnigram);
    }

    private function calculatePoints(string $word, string $omnigram): int
    {
        // Validate the word can be made from the omnigram
        if (!$this->omnigramService->isValidWord($word, $omnigram)) {
            throw new \RuntimeException('Word cannot be made from the current omnigram');
        }

        // Basic scoring: 1 point per letter
        // You can implement more complex scoring rules here
        return strlen($word);
    }

    private function getTimeRemaining(PlaySession $session): int
    {
        $endTime = $session->started_at->copy()->addHours(24);
        return max(0, $endTime->diffInSeconds(now()));
    }
} 