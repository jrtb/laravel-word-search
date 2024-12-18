<?php

namespace App\Services;

use App\Models\PlayerSession;
use Carbon\Carbon;

class PlayerSessionService
{
    /**
     * Record a player session and update streak information
     */
    public function recordSession(string $playerId): array
    {
        $today = Carbon::today();
        
        // Get the player's latest session
        $latestSession = PlayerSession::where('player_id', $playerId)
            ->orderBy('session_date', 'desc')
            ->first();
            
        if (!$latestSession) {
            // First ever session
            $session = PlayerSession::create([
                'player_id' => $playerId,
                'session_date' => $today,
                'current_streak' => 1,
                'highest_streak' => 1
            ]);

            return $this->formatResponse($session);
        }

        // If already recorded today, just return current stats
        if ($latestSession->session_date->isToday()) {
            return $this->formatResponse($latestSession);
        }

        // Calculate new streak
        $currentStreak = $latestSession->current_streak;
        $highestStreak = $latestSession->highest_streak;

        if ($latestSession->session_date->isYesterday()) {
            // Increment streak for consecutive days
            $currentStreak++;
            $highestStreak = max($currentStreak, $highestStreak);
        } else {
            // Reset streak if not consecutive
            $currentStreak = 1;
        }

        // Create new session record
        $session = PlayerSession::create([
            'player_id' => $playerId,
            'session_date' => $today,
            'current_streak' => $currentStreak,
            'highest_streak' => $highestStreak
        ]);

        return $this->formatResponse($session);
    }

    /**
     * Get current streak information for a player
     */
    public function getStreakInfo(string $playerId): array
    {
        $latestSession = PlayerSession::where('player_id', $playerId)
            ->orderBy('session_date', 'desc')
            ->first();

        if (!$latestSession) {
            return [
                'current_streak' => 0,
                'highest_streak' => 0,
                'last_session_date' => null
            ];
        }

        // If last session was more than a day ago, current streak is broken
        if ($latestSession->session_date->diffInDays(Carbon::today()) > 1) {
            return [
                'current_streak' => 0,
                'highest_streak' => $latestSession->highest_streak,
                'last_session_date' => $latestSession->session_date->toDateString()
            ];
        }

        return $this->formatResponse($latestSession);
    }

    private function formatResponse(PlayerSession $session): array
    {
        return [
            'current_streak' => $session->current_streak,
            'highest_streak' => $session->highest_streak,
            'last_session_date' => $session->session_date->toDateString()
        ];
    }
} 