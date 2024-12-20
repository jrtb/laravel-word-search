<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LongestWord;
use App\Services\PlayerIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Longest Word",
 *     description="External API endpoints for tracking longest words found by players. Players are identified using request fingerprinting based on User-Agent, Accept-Language headers, and IP address for consistent identity across sessions. Sessions are maintained for 24 hours from last activity."
 * )
 */
class LongestWordController extends Controller
{
    private PlayerIdentityService $playerIdentityService;

    public function __construct(PlayerIdentityService $playerIdentityService)
    {
        $this->playerIdentityService = $playerIdentityService;
    }

    /**
     * Get the current session ID or create a new one if needed.
     * A session is valid for 24 hours from the last activity.
     */
    private function getSessionId(string $playerId): string
    {
        // Try to find the latest session for this player
        $latestWord = LongestWord::where('player_id', $playerId)
            ->latest('created_at')
            ->first();
            
        if ($latestWord && $latestWord->created_at->diffInHours(now()) < 24) {
            // Reuse the session if it's less than 24 hours old
            return $latestWord->session_id;
        }
        
        // Create a new session ID if no recent session exists
        return Session::getId();
    }

    private function findExistingPlayerId(Request $request): string
    {
        return $this->playerIdentityService->findOrGeneratePlayerId($request);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/longest-word",
     *     operationId="storeLongestWord",
     *     summary="Submit a new word",
     *     description="Attempts to store a word if it's longer than the current longest word for the player. Player identity is maintained across sessions using browser fingerprinting. Sessions remain active for 24 hours from last activity.",
     *     tags={"Longest Word"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"word"},
     *             @OA\Property(property="word", type="string", example="extraordinary", description="The word to submit")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Word submission result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="is_longest", type="boolean", example=true, description="Whether this word became the player's new longest word"),
     *             @OA\Property(property="submitted_word", type="string", example="extraordinary", description="The word that was submitted"),
     *             @OA\Property(property="player_id", type="string", example="8f7d9c2e", description="SHA-256 hash of the player's browser fingerprint"),
     *             @OA\Property(property="session_id", type="string", example="sess_123abc", description="Current session ID. Same ID is reused within 24 hours.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - empty or invalid word"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'word' => ['required', 'string', 'min:1']
        ]);

        $word = $validated['word'];
        $playerId = $this->findExistingPlayerId($request);
        $sessionId = $this->getSessionId($playerId);

        // Get the current longest word for the player
        $currentLongest = LongestWord::where('player_id', $playerId)
            ->orderByRaw('LENGTH(word) DESC')
            ->first();
        
        // Only store if this word is longer than the current longest
        $isLongest = !$currentLongest || strlen($word) > strlen($currentLongest->word);
        
        if ($isLongest) {
            // Create a new record for this word
            LongestWord::create([
                'word' => $word,
                'session_id' => $sessionId,
                'player_id' => $playerId
            ]);
        }

        return response()->json([
            'success' => true,
            'is_longest' => $isLongest,
            'submitted_word' => $word,
            'player_id' => $playerId,
            'session_id' => $sessionId
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/longest-word",
     *     operationId="getLongestWord",
     *     summary="Get the player's longest word",
     *     description="Returns the longest word submitted by the player, identified by their browser fingerprint. Session information is included in the response.",
     *     tags={"Longest Word"},
     *     @OA\Response(
     *         response=200,
     *         description="Player's longest word",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="longest_word", type="string", example="extraordinary"),
     *             @OA\Property(property="length", type="integer", example=13),
     *             @OA\Property(property="player_id", type="string", example="8f7d9c2e"),
     *             @OA\Property(property="session_id", type="string", example="sess_123abc", description="Current session ID")
     *         )
     *     )
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $playerId = $this->findExistingPlayerId($request);
        $longestWord = LongestWord::where('player_id', $playerId)
            ->orderByRaw('LENGTH(word) DESC')
            ->first();

        return response()->json([
            'success' => true,
            'longest_word' => $longestWord?->word ?? '',
            'length' => $longestWord ? strlen($longestWord->word) : 0,
            'player_id' => $playerId,
            'session_id' => $this->getSessionId($playerId)
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/longest-word/top",
     *     operationId="getTopWords",
     *     summary="Get top longest words",
     *     description="Returns a list of the longest words submitted by all players, sorted by length. Includes session information for each submission.",
     *     tags={"Longest Word"},
     *     @OA\Response(
     *         response=200,
     *         description="List of top words",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="words",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="word", type="string", example="extraordinary"),
     *                     @OA\Property(property="player_id", type="string", example="8f7d9c2e"),
     *                     @OA\Property(property="length", type="integer", example=13),
     *                     @OA\Property(property="submitted_at", type="string", format="date-time"),
     *                     @OA\Property(property="session_id", type="string", example="sess_123abc")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function topWords(): JsonResponse
    {
        $words = LongestWord::select('word', 'player_id', 'created_at')
            ->orderByRaw('LENGTH(word) DESC')
            ->get()
            ->map(function ($record) {
                return [
                    'word' => $record->word,
                    'player_id' => $record->player_id,
                    'length' => strlen($record->word),
                    'submitted_at' => $record->created_at->toIso8601String(),
                    'session_id' => $record->session_id
                ];
            });

        return response()->json([
            'success' => true,
            'words' => $words
        ]);
    }
}
