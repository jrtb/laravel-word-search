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
 *     description="External API endpoints for tracking longest words found by players. Players are identified using request fingerprinting based on User-Agent, Accept-Language headers, and IP address for consistent identity across sessions."
 * )
 */
class LongestWordController extends Controller
{
    private PlayerIdentityService $playerIdentityService;

    public function __construct(PlayerIdentityService $playerIdentityService)
    {
        $this->playerIdentityService = $playerIdentityService;
    }

    private function getSessionId(): string
    {
        return Session::get('_id', Session::getId());
    }

    private function findExistingPlayerId(Request $request): string
    {
        $sessionId = $this->getSessionId();

        // First, try to find any record with this session
        $word = LongestWord::where('session_id', $sessionId)->first();
        if ($word) {
            // Pass the existing player ID to the service
            return $this->playerIdentityService->findOrGeneratePlayerId($request, $word->player_id);
        }

        // No existing session found, generate a new player ID
        $playerId = $this->playerIdentityService->findOrGeneratePlayerId($request);

        // Store this association for future lookups
        LongestWord::where('player_id', $playerId)
            ->update(['session_id' => $sessionId]);

        return $playerId;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/longest-word",
     *     operationId="storeLongestWord",
     *     summary="Submit a new word",
     *     description="Attempts to store a word if it's longer than the current longest word for the player. Player identity is maintained across sessions using browser fingerprinting (User-Agent, Accept-Language headers, and IP address).",
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
     *             @OA\Property(property="player_id", type="string", example="8f7d9c2e", description="SHA-256 hash of the player's browser fingerprint")
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
        $sessionId = $this->getSessionId();
        $playerId = $this->findExistingPlayerId($request);

        // Get user's current longest word by player_id
        $currentLongest = LongestWord::playerLongest($playerId)->first();

        $isLongest = !$currentLongest || strlen($word) > strlen($currentLongest->word);

        if ($isLongest) {
            // Create new longest word
            LongestWord::create([
                'word' => $word,
                'session_id' => $sessionId,
                'player_id' => $playerId
            ]);
        } else {
            // Even if not longest, update the session ID
            LongestWord::where('player_id', $playerId)
                ->update(['session_id' => $sessionId]);
        }

        return response()->json([
            'success' => true,
            'is_longest' => $isLongest,
            'submitted_word' => $word,
            'player_id' => $playerId
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/longest-word",
     *     operationId="getLongestWord",
     *     summary="Get current longest word",
     *     description="Retrieves the longest word stored for the current player. Player identity is maintained across sessions using browser fingerprinting (User-Agent, Accept-Language headers, and IP address), ensuring consistent results even with different session IDs.",
     *     tags={"Longest Word"},
     *     @OA\Response(
     *         response=200,
     *         description="Current longest word information",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="longest_word", type="string", example="extraordinary", nullable=true, description="The player's longest word, null if no words submitted"),
     *             @OA\Property(property="length", type="integer", example=13, description="Length of the longest word, 0 if no words submitted"),
     *             @OA\Property(property="player_id", type="string", example="8f7d9c2e", description="SHA-256 hash of the player's browser fingerprint, consistent across sessions")
     *         )
     *     )
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $sessionId = $this->getSessionId();
        $playerId = $this->findExistingPlayerId($request);
        
        // Get the longest word for this player
        $longestWord = LongestWord::playerLongest($playerId)->first();

        // Always update session ID
        if ($longestWord) {
            LongestWord::where('player_id', $playerId)
                ->update(['session_id' => $sessionId]);
        }

        return response()->json([
            'success' => true,
            'longest_word' => $longestWord ? $longestWord->word : null,
            'length' => $longestWord ? strlen($longestWord->word) : 0,
            'player_id' => $playerId
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/longest-word/top",
     *     operationId="getTopLongestWords",
     *     summary="Get top 10 longest words",
     *     description="Retrieves the top 10 longest words submitted by all players, ordered by word length. Each word includes the submitter's player ID (SHA-256 hash of their browser fingerprint), which remains consistent across their sessions.",
     *     tags={"Longest Word"},
     *     @OA\Response(
     *         response=200,
     *         description="Top 10 longest words",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="words",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="word", type="string", example="supercalifragilistic", description="The submitted word"),
     *                     @OA\Property(property="player_id", type="string", example="8f7d9c2e", description="SHA-256 hash of the player's browser fingerprint"),
     *                     @OA\Property(property="length", type="integer", example=20, description="Length of the word"),
     *                     @OA\Property(property="submitted_at", type="string", format="date-time", example="2024-03-15T10:30:00Z", description="When the word was submitted")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function topWords(): JsonResponse
    {
        $topWords = LongestWord::topLongest(10)
            ->get()
            ->map(function ($word) {
                return [
                    'word' => $word->word,
                    'player_id' => $word->player_id,
                    'length' => strlen($word->word),
                    'submitted_at' => $word->created_at
                ];
            });

        return response()->json([
            'success' => true,
            'words' => $topWords
        ]);
    }
}
