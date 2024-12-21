<?php

namespace App\Services;

use App\Models\PlaySession;
use App\Models\PlaySessionWord;
use App\Models\LongestWord;
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

        $longestWord = LongestWord::where('player_id', $playerId)
            ->orderByRaw('LENGTH(word) DESC')
            ->first();

        return [
            'session_id' => $session->id,
            'omnigram' => $session->omnigram,
            'started_at' => $session->started_at->toIso8601String(),
            'words' => $session->words->map(fn(PlaySessionWord $word) => [
                'word' => $word->word,
            ])->values(),
            'time_remaining' => $this->getTimeRemaining($session),
            'longest_word' => $longestWord?->word ?? '',
            'longest_word_length' => $longestWord ? strlen($longestWord->word) : 0,
        ];
    }

    public function submitWord(string $playerId, string $word): array
    {
        $session = PlaySession::getCurrentSession($playerId);

        if (!$session) {
            $session = $this->startNewSession($playerId);
        }

        try {
            $sessionWord = $session->addWord($word);

            return [
                'success' => true,
                'word' => $sessionWord->word,
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
            ->withCount('words')
            ->orderByDesc('words_count')
            ->limit($limit)
            ->get()
            ->map(fn(PlaySession $session) => [
                'player_id' => $session->player_id,
                'word_count' => $session->words_count,
                'date' => $session->started_at->toDateString(),
            ]);
    }

    private function startNewSession(string $playerId): PlaySession
    {
        $omnigram = $this->omnigramService->getRandomOmnigram();
        return PlaySession::startNewSession($playerId, $omnigram);
    }

    private function getTimeRemaining(PlaySession $session): int
    {
        $endTime = $session->started_at->copy()->addHours(24);
        return max(0, $endTime->diffInSeconds(now()));
    }
} 