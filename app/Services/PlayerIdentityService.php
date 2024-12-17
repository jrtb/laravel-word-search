<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\LongestWord;

class PlayerIdentityService
{
    /**
     * Generate a fingerprint for the current request to identify returning players.
     */
    public function generateFingerprint(Request $request): string
    {
        // Get raw values to ensure we're getting the actual request data
        $ip = $request->server('REMOTE_ADDR');
        $userAgent = $request->header('User-Agent');
        $acceptLanguage = $request->header('Accept-Language');

        // Create a unique string combining all identifiers
        $identifierString = implode('|', array_filter([
            $ip,
            $userAgent,
            $acceptLanguage,
        ]));

        // Hash the identifier string
        return hash('sha256', $identifierString);
    }

    /**
     * Find or generate a player ID for the current request.
     */
    public function findOrGeneratePlayerId(Request $request, ?string $existingPlayerId = null): string
    {
        // If we have an existing player ID, verify it's valid and return it
        if ($existingPlayerId && LongestWord::where('player_id', $existingPlayerId)->exists()) {
            return $existingPlayerId;
        }

        // Generate fingerprint for the current request
        $fingerprint = $this->generateFingerprint($request);

        // Look for any existing player with this fingerprint
        $existingWord = LongestWord::where('player_id', $fingerprint)->first();
        if ($existingWord) {
            return $fingerprint;
        }

        // If no existing player found, use the fingerprint as the new player ID
        return $fingerprint;
    }
} 