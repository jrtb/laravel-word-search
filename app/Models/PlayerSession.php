<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'session_date',
        'current_streak',
        'highest_streak'
    ];

    protected $casts = [
        'session_date' => 'date',
        'current_streak' => 'integer',
        'highest_streak' => 'integer'
    ];
} 