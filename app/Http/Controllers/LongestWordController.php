<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LongestWord;

class LongestWordController extends Controller
{
    public function store(Request $request)
    {
        $word = $request->input('word');
        $playerId = $request->input('player_id', session()->getId());
        $sessionId = $request->input('session_id', session()->getId());

        // Update or create the record
        $longestWord = LongestWord::updateOrCreate(
            ['player_id' => $playerId],
            [
                'word' => $word,
                'session_id' => $sessionId,
            ]
        );

        return response()->json([
            'success' => true,
            'is_longest' => true,
            'submitted_word' => $word
        ]);
    }

    public function show(Request $request)
    {
        $playerId = $request->input('player_id', session()->getId());
        $longestWord = LongestWord::where('player_id', $playerId)->first();

        return response()->json([
            'success' => true,
            'longest_word' => $longestWord?->word,
            'length' => $longestWord?->word ? strlen($longestWord->word) : 0,
            'player_id' => $playerId
        ]);
    }
} 