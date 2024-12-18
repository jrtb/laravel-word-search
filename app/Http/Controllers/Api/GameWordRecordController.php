<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameWordRecord;
use App\Services\PlayerIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Game Word Records",
 *     description="API endpoints for managing game word count records"
 * )
 */
class GameWordRecordController extends Controller
{
    protected PlayerIdentityService $playerIdentityService;

    public function __construct(PlayerIdentityService $playerIdentityService)
    {
        $this->playerIdentityService = $playerIdentityService;
    }

    /**
     * Get the highest word count for the current player
     * 
     * @OA\Get(
     *     path="/api/v1/game-words/highest",
     *     tags={"Game Word Records"},
     *     summary="Get player's highest word count",
     *     description="Returns the highest number of words found in a single game for the current player",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="highest_word_count", type="integer", example=42),
     *             @OA\Property(
     *                 property="player_id",
     *                 type="string",
     *                 description="SHA-256 hash of browser fingerprint",
     *                 example="8f7d9c2e"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests - rate limit exceeded (60 per minute)"
     *     )
     * )
     */
    public function getHighestWordCount(Request $request): JsonResponse
    {
        $playerId = $this->playerIdentityService->findOrGeneratePlayerId($request);
        $highestWordCount = GameWordRecord::getHighestWordCount($playerId);

        return response()->json([
            'success' => true,
            'highest_word_count' => $highestWordCount,
            'player_id' => $playerId
        ]);
    }

    /**
     * Get the top 10 word counts across all players
     * 
     * @OA\Get(
     *     path="/api/v1/game-words/top",
     *     tags={"Game Word Records"},
     *     summary="Get top word counts",
     *     description="Returns the top 10 highest word counts across all players",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="records",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="player_id", type="string", example="8f7d9c2e"),
     *                     @OA\Property(property="highest_word_count", type="integer", example=42),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-03-20T10:30:00Z")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getTopWordCounts(): JsonResponse
    {
        $records = GameWordRecord::getTopWordCounts();

        return response()->json([
            'success' => true,
            'records' => $records
        ]);
    }

    /**
     * Update the word count for the current game session
     * 
     * @OA\Post(
     *     path="/api/v1/game-words/update",
     *     tags={"Game Word Records"},
     *     summary="Update game word count",
     *     description="Updates the word count for the current game and updates the highest count if exceeded",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"word_count"},
     *             @OA\Property(
     *                 property="word_count",
     *                 type="integer",
     *                 description="Number of words found in the current game",
     *                 example=15
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="word_count", type="integer", example=15),
     *             @OA\Property(property="highest_word_count", type="integer", example=42),
     *             @OA\Property(property="is_new_record", type="boolean", example=false),
     *             @OA\Property(
     *                 property="player_id",
     *                 type="string",
     *                 description="SHA-256 hash of browser fingerprint",
     *                 example="8f7d9c2e"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="word_count",
     *                     type="array",
     *                     @OA\Items(type="string", example="The word count field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests - rate limit exceeded (60 per minute)"
     *     )
     * )
     */
    public function updateWordCount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'word_count' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $playerId = $this->playerIdentityService->findOrGeneratePlayerId($request);
        $result = GameWordRecord::updateWordCount($playerId, $request->input('word_count'));

        return response()->json([
            'success' => true,
            'word_count' => $result['word_count'],
            'highest_word_count' => $result['highest_word_count'],
            'is_new_record' => $result['is_new_record'],
            'player_id' => $playerId
        ]);
    }
} 