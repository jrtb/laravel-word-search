<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaySessionWord extends Model
{
    use HasFactory;

    protected $fillable = [
        'play_session_id',
        'word',
    ];

    public function playSession(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class);
    }
} 