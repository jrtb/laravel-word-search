<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use App\Models\LongestWord;

class PlayerIdentityService
{
    private const PLAYER_ID_SESSION_KEY = 'player_id';
    private const FINGERPRINT_SESSION_KEY = 'fingerprint';
    private const BROWSER_ID_SESSION_KEY = 'browser_id';

    /**
     * Generate a fingerprint for the current request to identify returning players.
     * Uses multiple factors to create a unique identifier while maintaining privacy.
     */
    public function generateFingerprint(Request $request): string
    {
        // Collect all relevant headers that could identify a browser
        $headers = [
            'user-agent' => $request->header('User-Agent', ''),
            'accept-language' => $request->header('Accept-Language', ''),
            'accept-encoding' => $request->header('Accept-Encoding', ''),
            'accept-charset' => $request->header('Accept-Charset', ''),
            'sec-ch-ua' => $request->header('Sec-CH-UA', ''),
            'sec-ch-ua-mobile' => $request->header('Sec-CH-UA-Mobile', ''),
            'sec-ch-ua-platform' => $request->header('Sec-CH-UA-Platform', ''),
        ];

        // Sort headers to ensure consistent order
        ksort($headers);

        // Create a unique string combining all headers
        $fingerprintData = implode('|', array_filter($headers));

        // Add a salt to make the fingerprint more unique
        $salt = config('app.key', 'default-salt');
        
        // Generate a unique hash
        return hash('sha256', $fingerprintData . $salt);
    }

    /**
     * Find or generate a player ID for the current request.
     * Maintains consistent player identification across sessions while respecting privacy.
     */
    public function findOrGeneratePlayerId(Request $request, ?string $existingPlayerId = null): string
    {
        // First, try to get the player ID from the session
        $sessionPlayerId = Session::get(self::PLAYER_ID_SESSION_KEY);
        $sessionFingerprint = Session::get(self::FINGERPRINT_SESSION_KEY);
        
        // Generate current fingerprint
        $currentFingerprint = $this->generateFingerprint($request);

        // If we have a valid session player ID and matching fingerprint, use it
        if ($sessionPlayerId && $sessionFingerprint === $currentFingerprint) {
            return $sessionPlayerId;
        }

        // If we have an existing player ID from the request, verify it
        if ($existingPlayerId && LongestWord::where('player_id', $existingPlayerId)->exists()) {
            $this->storePlayerIdentity($existingPlayerId, $currentFingerprint);
            return $existingPlayerId;
        }

        // Look for any existing player with this fingerprint
        $existingWord = LongestWord::where('player_id', $currentFingerprint)->first();
        if ($existingWord) {
            $this->storePlayerIdentity($currentFingerprint, $currentFingerprint);
            return $currentFingerprint;
        }

        // If no existing player found, use the fingerprint as the new player ID
        $this->storePlayerIdentity($currentFingerprint, $currentFingerprint);
        return $currentFingerprint;
    }

    /**
     * Store the player identity in the session.
     */
    private function storePlayerIdentity(string $playerId, string $fingerprint): void
    {
        Session::put([
            self::PLAYER_ID_SESSION_KEY => $playerId,
            self::FINGERPRINT_SESSION_KEY => $fingerprint
        ]);
        Session::save();
    }
} 