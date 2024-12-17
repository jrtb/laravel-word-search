<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LongestWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Longest Word",
 *     description="External API endpoints for tracking longest words found by players"
 * )
 */
class LongestWordController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/longest-word",
     *     operationId="storeLongestWord",
     *     summary="Submit a new word",
     *     description="Attempts to store a word if it's longer than the current longest word for the session. Each player maintains their own longest word record.",
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
     *             @OA\Property(property="is_longest", type="boolean", example=true),
     *             @OA\Property(property="submitted_word", type="string", example="extraordinary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - empty or invalid word"
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests - rate limit exceeded",
     *         @OA\Header(
     *             header="X-RateLimit-Limit",
     *             description="Number of requests allowed per minute",
     *             @OA\Schema(type="integer", example=60)
     *         ),
     *         @OA\Header(
     *             header="X-RateLimit-Remaining",
     *             description="Number of requests remaining in current time window",
     *             @OA\Schema(type="integer", example=59)
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'word' => ['required', 'string', 'min:1']
        ]);

        $word = $validated['word'];
        $userId = Session::get('_id', Session::getId());

        // Get user's current longest word
        $currentLongest = LongestWord::where('user_id', $userId)
            ->orderByRaw('LENGTH(word) DESC')
            ->first();

        $isLongest = !$currentLongest || strlen($word) > strlen($currentLongest->word);

        if ($isLongest) {
            LongestWord::create([
                'word' => $word,
                'user_id' => $userId
            ]);
        }

        return response()->json([
            'success' => true,
            'is_longest' => $isLongest,
            'submitted_word' => $word
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/longest-word",
     *     operationId="getLongestWord",
     *     summary="Get current longest word",
     *     description="Retrieves the longest word stored for the current session. Each player sees only their own longest word.",
     *     tags={"Longest Word"},
     *     @OA\Response(
     *         response=200,
     *         description="Current longest word information",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="longest_word", type="string", example="extraordinary", nullable=true),
     *             @OA\Property(property="length", type="integer", example=13)
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests - rate limit exceeded",
     *         @OA\Header(
     *             header="X-RateLimit-Limit",
     *             description="Number of requests allowed per minute",
     *             @OA\Schema(type="integer", example=60)
     *         ),
     *         @OA\Header(
     *             header="X-RateLimit-Remaining",
     *             description="Number of requests remaining in current time window",
     *             @OA\Schema(type="integer", example=59)
     *         )
     *     )
     * )
     */
    public function show(): JsonResponse
    {
        $userId = Session::get('_id', Session::getId());
        
        // Get the longest word for this user
        $longestWord = LongestWord::where('user_id', $userId)
            ->orderByRaw('LENGTH(word) DESC')
            ->first();

        return response()->json([
            'success' => true,
            'longest_word' => $longestWord ? $longestWord->word : null,
            'length' => $longestWord ? strlen($longestWord->word) : 0
        ])->withHeaders([
            'X-RateLimit-Remaining' => app('request')->header('X-RateLimit-Remaining'),
            'X-RateLimit-Limit' => 60
        ]);
    }
}
