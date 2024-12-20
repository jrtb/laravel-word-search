<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class OmnigramService
{
    private const CACHE_KEY = 'omnigrams_list';
    private const CACHE_TTL = 86400; // 24 hours

    public function getRandomOmnigram(): string
    {
        $omnigrams = $this->getOmnigrams();
        return $omnigrams[array_rand($omnigrams)];
    }

    public function isValidWord(string $word, string $omnigram): bool
    {
        // Convert both to lowercase for case-insensitive comparison
        $word = strtolower($word);
        $omnigram = strtolower($omnigram);

        // Get character frequency in the omnigram
        $omnigramChars = array_count_values(str_split($omnigram));

        // Check if each character in the word can be made from the omnigram
        $wordChars = array_count_values(str_split($word));
        foreach ($wordChars as $char => $count) {
            if (!isset($omnigramChars[$char]) || $omnigramChars[$char] < $count) {
                return false;
            }
        }

        // Additional validation can be added here (e.g., minimum length, dictionary check)
        return true;
    }

    private function getOmnigrams(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $content = Storage::disk('s3')->get('assets/list1.txt');
            $words = array_filter(explode("\n", $content));
            
            // Filter for words that meet omnigram criteria (8+ letters, 8 unique letters, contains 'S')
            return array_values(array_filter($words, function ($word) {
                $word = trim($word);
                return strlen($word) >= 8 && 
                       count(array_unique(str_split($word))) === 8 && 
                       stripos($word, 'S') !== false;
            }));
        });
    }
} 