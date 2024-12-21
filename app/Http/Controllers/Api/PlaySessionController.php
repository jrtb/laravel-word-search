<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlayerIdentityService;
use App\Services\PlaySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Play Sessions",
 *     description="Endpoints for managing 24-hour play sessions and word submissions."
 * )
 */
class PlaySessionController extends Controller
{
    private PlayerIdentityService $playerIdentityService;
    private PlaySessionService $playSessionService;

    public function __construct(
        PlayerIdentityService $playerIdentityService,
        PlaySessionService $playSessionService
    ) {
        $this->playerIdentityService = $playerIdentityService;
        $this->playSessionService = $playSessionService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/play-session/current",
     *     operationId="getCurrentSession",
     *     summary="Get current play session",
     *     description="Get the current play session for the player, creating a new one if needed. Sessions last 24 hours from creation.",
     *     tags={"Play Sessions"},
     *     @OA\Response(
     *         response=200,
     *         description="Current play session information",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="session_id", type="integer", example=1),
     *             @OA\Property(property="omnigram", type="string", example="STARLIGHT"),
     *             @OA\Property(property="started_at", type="string", format="date-time"),
     *             @OA\Property(property="time_remaining", type="integer", description="Seconds remaining in session"),
     *             @OA\Property(property="words", type="array", @OA\Items(
     *                 @OA\Property(property="word", type="string", example="STAR")
     *             )),
     *             @OA\Property(property="longest_word", type="string", example="EXTRAORDINARY", description="Player's longest word found so far"),
     *             @OA\Property(property="longest_word_length", type="integer", example=13, description="Length of player's longest word")
     *         )
     *     )
     * )
     */
    public function current(Request $request): JsonResponse
    {
        $playerId = $this->playerIdentityService->findOrGeneratePlayerId($request);
        $session = $this->playSessionService->getCurrentSession($playerId);

        return response()->json([
            'success' => true,
            ...$session
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/play-session/submit-word",
     *     operationId="submitWord",
     *     summary="Submit a word to the current play session",
     *     description="Submit a word found in the current play session. Creates a new session if needed. Validates word against current session's omnigram. Also checks and updates the player's longest word record.",
     *     tags={"Play Sessions"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"word"},
     *             @OA\Property(property="word", type="string", example="STAR", description="The word to submit")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Word submission result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="word", type="string", example="STAR", description="The submitted word if valid"),
     *             @OA\Property(property="is_longest", type="boolean", example=true, description="Whether this word became the player's new longest word"),
     *             @OA\Property(property="longest_word", type="string", example="STARLIGHT", description="The player's current longest word"),
     *             @OA\Property(property="longest_word_length", type="integer", example=9, description="Length of the player's longest word")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid request - word parameter missing or invalid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="The word field is required")
     *         )
     *     )
     * )
     */
    public function submitWord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'word' => ['required', 'string', 'min:1']
        ]);

        $playerId = $this->playerIdentityService->findOrGeneratePlayerId($request);
        $result = $this->playSessionService->submitWord($playerId, $validated['word']);

        // Get the player's longest word information and its session
        $longestWord = \App\Models\LongestWord::where('player_id', $playerId)
            ->orderByRaw('LENGTH(word) DESC')
            ->first();

        // Check if this word is longer than the current longest
        $word = $validated['word'];
        $isLongest = !$longestWord || strlen($word) > strlen($longestWord->word);
        
        // Get the session ID - reuse the existing session if it's less than 24 hours old
        $sessionId = $result['session_id'] ?? null;
        if (!$sessionId && $longestWord && $longestWord->created_at->diffInHours(now()) < 24) {
            $sessionId = $longestWord->session_id;
        }
        $sessionId = $sessionId ?? session()->getId();
        
        if ($isLongest) {
            // If there's an existing record, delete it since we have a longer word
            if ($longestWord) {
                $longestWord->delete();
            }
            
            // Create a new record for this word
            \App\Models\LongestWord::create([
                'word' => $word,
                'session_id' => $sessionId,
                'player_id' => $playerId
            ]);
            
            // Update longest word info
            $longestWord = \App\Models\LongestWord::where('player_id', $playerId)
                ->orderByRaw('LENGTH(word) DESC')
                ->first();
        }

        return response()->json([
            'success' => true,
            'word' => $word,
            'is_longest' => $isLongest,
            'longest_word' => $longestWord?->word ?? '',
            'longest_word_length' => $longestWord ? strlen($longestWord->word) : 0,
            'player_id' => $playerId,
            'session_id' => $sessionId,
            ...$result
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/play-session/top-scores",
     *     operationId="getTopScores",
     *     summary="Get top word counts",
     *     description="Get the top word counts from completed play sessions, ordered by number of words found.",
     *     tags={"Play Sessions"},
     *     @OA\Response(
     *         response=200,
     *         description="Top word counts list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="scores", type="array", @OA\Items(
     *                 @OA\Property(property="player_id", type="string"),
     *                 @OA\Property(property="word_count", type="integer"),
     *                 @OA\Property(property="date", type="string", format="date")
     *             ))
     *         )
     *     )
     * )
     */
    public function topScores(): JsonResponse
    {
        $scores = $this->playSessionService->getTopScores();

        return response()->json([
            'success' => true,
            'scores' => $scores,
        ]);
    }
} 