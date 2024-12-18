<?php

namespace Database\Factories;

use App\Models\LongestWord;
use Illuminate\Database\Eloquent\Factories\Factory;

class LongestWordFactory extends Factory
{
    protected $model = LongestWord::class;

    public function definition(): array
    {
        return [
            'word' => $this->faker->word,
            'session_id' => $this->faker->uuid,
            'player_id' => hash('sha256', $this->faker->uuid),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
} 