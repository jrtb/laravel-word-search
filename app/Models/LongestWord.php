<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LongestWord extends Model
{
    protected $fillable = ['word', 'session_id', 'player_id'];

    /**
     * Scope a query to get the top longest words.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTopLongest($query, $limit = 10)
    {
        return $query->select('word', 'player_id', 'created_at')
            ->orderByRaw('LENGTH(word) DESC')
            ->limit($limit);
    }
}
