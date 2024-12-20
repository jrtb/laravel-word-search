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
 *     description="Endpoints for managing 24-hour play sessions and word submissions"
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
     *     description="Get the current play session for the player, creating a new one if needed",
     *     tags={"Play Sessions"},
     *     @OA\Response(
     *         response=200,
     *         description="Current play session information",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="session_id", type="integer", example=1),
     *             @OA\Property(property="omnigram", type="string", example="STARLIGHT"),
     *             @OA\Property(property="score", type="integer", example=42),
     *             @OA\Property(property="started_at", type="string", format="date-time"),
     *             @OA\Property(property="time_remaining", type="integer", description="Seconds remaining in session"),
     *             @OA\Property(property="words", type="array", @OA\Items(
     *                 @OA\Property(property="word", type="string"),
     *                 @OA\Property(property="points", type="integer")
     *             ))
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
     *     summary="Submit a word for the current session",
     *     description="Submit a word to be scored in the current play session",
     *     tags={"Play Sessions"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"word"},
     *             @OA\Property(property="word", type="string", example="STAR")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Word submission result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="word", type="string", example="STAR"),
     *             @OA\Property(property="points", type="integer", example=4),
     *             @OA\Property(property="total_score", type="integer", example=46)
     *         )
     *     )
     * )
     */
    public function submitWord(Request $request): JsonResponse
    {
        $request->validate([
            'word' => 'required|string|min:1',
        ]);

        $playerId = $this->playerIdentityService->findOrGeneratePlayerId($request);
        $result = $this->playSessionService->submitWord($playerId, $request->input('word'));

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/play-session/top-scores",
     *     operationId="getTopScores",
     *     summary="Get top scores",
     *     description="Get the top scores from completed play sessions",
     *     tags={"Play Sessions"},
     *     @OA\Response(
     *         response=200,
     *         description="Top scores list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="scores", type="array", @OA\Items(
     *                 @OA\Property(property="player_id", type="string"),
     *                 @OA\Property(property="score", type="integer"),
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