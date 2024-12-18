<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlayerIdentityService;
use App\Services\PlayerSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Player Session",
 *     description="Endpoints for tracking player sessions and streaks. Sessions are recorded when players load the game."
 * )
 */
class PlayerSessionController extends Controller
{
    private PlayerIdentityService $playerIdentityService;
    private PlayerSessionService $playerSessionService;

    public function __construct(
        PlayerIdentityService $playerIdentityService,
        PlayerSessionService $playerSessionService
    ) {
        $this->playerIdentityService = $playerIdentityService;
        $this->playerSessionService = $playerSessionService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/session",
     *     operationId="recordSession",
     *     summary="Record a player session",
     *     description="Records that a player has started a game session. Used for tracking daily streaks.",
     *     tags={"Player Session"},
     *     @OA\Response(
     *         response=200,
     *         description="Session recorded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="current_streak", type="integer", example=3),
     *             @OA\Property(property="highest_streak", type="integer", example=5),
     *             @OA\Property(property="last_session_date", type="string", format="date", example="2024-03-19")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $playerId = $this->playerIdentityService->findOrGeneratePlayerId($request);
        $sessionInfo = $this->playerSessionService->recordSession($playerId);

        return response()->json([
            'success' => true,
            ...$sessionInfo
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/session/streak",
     *     operationId="getSessionStreak",
     *     summary="Get player's streak information",
     *     description="Retrieves the current and highest streak information for the player based on daily game sessions.",
     *     tags={"Player Session"},
     *     @OA\Response(
     *         response=200,
     *         description="Player's streak information",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="current_streak", type="integer", example=3),
     *             @OA\Property(property="highest_streak", type="integer", example=5),
     *             @OA\Property(property="last_session_date", type="string", format="date", example="2024-03-19")
     *         )
     *     )
     * )
     */
    public function getStreak(Request $request): JsonResponse
    {
        $playerId = $this->playerIdentityService->findOrGeneratePlayerId($request);
        $streakInfo = $this->playerSessionService->getStreakInfo($playerId);

        return response()->json([
            'success' => true,
            ...$streakInfo
        ]);
    }
} 