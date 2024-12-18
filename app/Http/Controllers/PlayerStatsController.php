<?php

namespace App\Http\Controllers;

use App\Models\GameWordRecord;
use App\Models\LongestWord;
use Illuminate\Http\Request;

class PlayerStatsController extends Controller
{
    public function index()
    {
        $topWordCounts = GameWordRecord::orderBy('highest_word_count', 'desc')
            ->take(10)
            ->get();

        return view('player-stats.index', compact('topWordCounts'));
    }
} 