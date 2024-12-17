<?php

namespace App\Services;

use Illuminate\Http\Request;

class PlayerIdentityService
{
    /**
     * Generate a fingerprint for the current request to identify returning players.
     */
    public function generateFingerprint(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->ip(),
            $request->userAgent(),
            $request->header('Accept-Language', ''),
            // Add more identifiers as needed
        ]));
    }

    /**
     * Find or generate a player ID for the current request.
     */
    public function findOrGeneratePlayerId(Request $request, ?string $existingPlayerId = null): string
    {
        if ($existingPlayerId) {
            return $existingPlayerId;
        }

        return $this->generateFingerprint($request);
    }
} 