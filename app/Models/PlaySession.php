<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'omnigram',
        'score',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'score' => 'integer',
    ];

    public function words(): HasMany
    {
        return $this->hasMany(PlaySessionWord::class);
    }

    public function isActive(): bool
    {
        return !$this->ended_at && 
               $this->started_at->diffInHours(now()) < 24;
    }

    public function shouldEnd(): bool
    {
        return !$this->ended_at && 
               $this->started_at->diffInHours(now()) >= 24;
    }

    public static function getCurrentSession(string $playerId): ?self
    {
        $session = static::where('player_id', $playerId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if ($session && $session->shouldEnd()) {
            $session->ended_at = now();
            $session->save();
            return null;
        }

        return $session && $session->isActive() ? $session : null;
    }

    public static function startNewSession(string $playerId, string $omnigram): self
    {
        // End any existing sessions
        static::where('player_id', $playerId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        // Start time should be midnight Eastern time
        $startTime = now()->setTimezone('America/New_York')->startOfDay()->setTimezone(config('app.timezone'));

        return static::create([
            'player_id' => $playerId,
            'omnigram' => $omnigram,
            'score' => 0,
            'started_at' => $startTime,
        ]);
    }

    public function addWord(string $word, int $points): PlaySessionWord
    {
        if (!$this->isActive()) {
            throw new \RuntimeException('Cannot add words to an inactive session');
        }

        $sessionWord = $this->words()->create([
            'word' => $word,
            'points' => $points,
        ]);

        $this->increment('score', $points);

        return $sessionWord;
    }
} 