<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LongestWord extends Model
{
    use HasFactory;

    protected $fillable = [
        'word',
        'session_id',
        'player_id'
    ];

    /**
     * Scope a query to get the top longest words.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $limit
     * @param bool $uniqueByPlayer Whether to return only one word per player
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTopLongest($query, int $limit = 10, bool $uniqueByPlayer = true)
    {
        $query = $query->select('word', 'player_id', 'created_at')
            ->orderByRaw('LENGTH(word) DESC');

        if ($uniqueByPlayer) {
            // Use a subquery to get the longest word per player
            $query->whereIn('id', function ($subquery) {
                $subquery->select('id')
                    ->from('longest_words as lw')
                    ->whereRaw('lw.player_id = longest_words.player_id')
                    ->orderByRaw('LENGTH(word) DESC')
                    ->limit(1);
            });
        }

        return $query->limit($limit);
    }

    /**
     * Scope a query to get the longest word for a specific player.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $playerId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePlayerLongest($query, string $playerId)
    {
        return $query->where('player_id', $playerId)
            ->orderByRaw('LENGTH(word) DESC');
    }
}
