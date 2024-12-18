<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameWordRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'word_count',
        'highest_word_count'
    ];

    /**
     * Get the highest word count for a player
     *
     * @param string $playerId
     * @return int
     */
    public static function getHighestWordCount(string $playerId): int
    {
        $record = static::where('player_id', $playerId)->first();
        return $record ? $record->highest_word_count : 0;
    }

    /**
     * Update the word count for a player's game session
     *
     * @param string $playerId
     * @param int $wordCount
     * @return array
     */
    public static function updateWordCount(string $playerId, int $wordCount): array
    {
        $record = static::firstOrNew(['player_id' => $playerId]);
        $record->word_count = $wordCount;
        
        $isHighest = $wordCount > $record->highest_word_count;
        if ($isHighest) {
            $record->highest_word_count = $wordCount;
        }
        
        $record->save();
        
        return [
            'word_count' => $wordCount,
            'highest_word_count' => $record->highest_word_count,
            'is_new_record' => $isHighest
        ];
    }
} 