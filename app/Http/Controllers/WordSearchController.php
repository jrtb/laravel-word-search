<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Word Search",
 *     description="Internal API endpoints for word searching functionality"
 * )
 */
class WordSearchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/",
     *     summary="Display the word search interface",
     *     tags={"Word Search"},
     *     @OA\Response(
     *         response=200,
     *         description="Word search view"
     *     )
     * )
     */
    public function index()
    {
        return view('word-search');
    }

    /**
     * @OA\Post(
     *     path="/search",
     *     summary="Search for words based on pattern",
     *     description="Search through specialized word lists based on a query pattern. Protected by CSRF token.",
     *     tags={"Word Search"},
     *     security={{"csrf":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"query", "list"},
     *             @OA\Property(property="query", type="string", example="example", description="The search pattern"),
     *             @OA\Property(property="list", type="string", enum={"omnigrams", "wordchecker", "both"}, example="both", description="Which list to search in")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="results",
     *                 type="object",
     *                 @OA\Property(property="omnigrams", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="wordchecker", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function search(Request $request)
    {
        try {
            $query = $request->input('query');
            $list = $request->input('list');

            $s3 = Storage::disk('s3');
            
            $results = [
                'omnigrams' => [],
                'wordchecker' => []
            ];

            // Define file paths
            $list1Path = 'assets/list1.txt';
            $list2Path = 'assets/list2.txt';

            // Check if files exist
            $list1Exists = $s3->exists($list1Path);
            $list2Exists = $s3->exists($list2Path);

            if (!$list1Exists || !$list2Exists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Word list files not found'
                ]);
            }

            try {
                // Get word lists from cache or S3
                $list1Words = Cache::remember('list1_words', 86400, function () use ($s3, $list1Path) {
                    $list1Content = $s3->get($list1Path);
                    return array_filter(explode("\n", $list1Content), 'trim');
                });

                $list2Words = Cache::remember('list2_words', 86400, function () use ($s3, $list2Path) {
                    $list2Content = $s3->get($list2Path);
                    return array_filter(explode("\n", $list2Content), 'trim');
                });
            } catch (\Exception $e) {
                Log::error('Failed to read word lists: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to read word lists'
                ]);
            }

            if ($list === 'omnigrams' || $list === 'both') {
                $results['omnigrams'] = array_values(array_filter($list1Words, function($word) use ($query) {
                    return stripos(trim($word), $query) !== false;
                }));
            }

            if ($list === 'wordchecker' || $list === 'both') {
                $results['wordchecker'] = array_values(array_filter($list2Words, function($word) use ($query) {
                    return stripos(trim($word), $query) !== false;
                }));
            }

            return response()->json([
                'success' => true,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Search error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while searching'
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/search-frequency",
     *     summary="Search for words based on frequency",
     *     description="Search for words that meet or exceed a specified frequency threshold. Protected by CSRF token.",
     *     tags={"Word Search"},
     *     security={{"csrf":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"frequency"},
     *             @OA\Property(property="frequency", type="number", format="float", example=0.0000009, description="Minimum frequency threshold")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total_count", type="integer", example=150),
     *             @OA\Property(property="words", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function searchFrequency(Request $request)
    {
        try {
            // Set execution time to 5 minutes
            set_time_limit(300);
            // Increase memory limit to 256MB
            ini_set('memory_limit', '256M');
            
            Log::info('Starting frequency search', ['request' => $request->all()]);
            
            $frequency = $request->input('frequency');
            
            if (!is_numeric($frequency) || $frequency < 0) {
                Log::warning('Invalid frequency value', ['frequency' => $frequency]);
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid frequency value'
                ]);
            }

            try {
                $s3 = Storage::disk('s3');
            } catch (\Exception $e) {
                Log::error('S3 connection error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to connect to storage'
                ]);
            }

            $frequencyPath = 'assets/processed_frequencies.csv';
            
            try {
                if (!$s3->exists($frequencyPath)) {
                    Log::error('Frequency file not found', ['path' => $frequencyPath]);
                    return response()->json([
                        'success' => false,
                        'error' => 'Frequency data file not found'
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error checking frequency file existence: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'error' => 'Error accessing frequency data'
                ]);
            }

            // Create a temporary file to store the CSV
            $tempFile = tempnam(sys_get_temp_dir(), 'freq_');
            try {
                // Stream the file from S3 to the temporary file
                file_put_contents($tempFile, $s3->get($frequencyPath));
                
                // Open the file for reading
                $handle = fopen($tempFile, 'r');
                if ($handle === false) {
                    throw new \Exception('Failed to open temporary file');
                }

                // Skip header row
                $header = fgetcsv($handle);
                if ($header === false) {
                    throw new \Exception('Failed to read CSV header');
                }

                Log::info('Processing frequency data', ['header' => $header]);

                $matchingWords = [];
                $totalCount = 0;
                $frequency = floatval($frequency);
                $processedLines = 0;
                $chunkSize = 5000; // Increased chunk size for better performance

                // Process the file line by line instead of loading chunks into memory
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) < 2) {
                        continue;
                    }

                    $word = trim($data[0]);
                    $wordFrequency = floatval($data[1]);

                    if ($wordFrequency >= $frequency) {
                        $totalCount++;
                        if (count($matchingWords) < 200) {
                            $matchingWords[] = $word;
                        }
                    }
                    
                    $processedLines++;

                    // Log progress every 100,000 lines
                    if ($processedLines % 100000 === 0) {
                        Log::info('Processing progress', [
                            'lines_processed' => $processedLines,
                            'matches_found' => $totalCount
                        ]);
                    }

                    // If we have enough matches and have processed at least 100,000 lines,
                    // we can stop early as we're unlikely to find significantly different results
                    if ($totalCount > 200 && $processedLines > 100000) {
                        break;
                    }
                }

                fclose($handle);
                unlink($tempFile); // Delete temporary file

                Log::info('Completed frequency search', [
                    'total_matches' => $totalCount,
                    'sample_size' => count($matchingWords),
                    'processed_lines' => $processedLines
                ]);

                return response()->json([
                    'success' => true,
                    'total_count' => $totalCount,
                    'words' => $matchingWords
                ]);

            } catch (\Throwable $e) {
                // Clean up temporary file if it exists
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                throw $e;
            }

        } catch (\Throwable $e) {
            Log::error('Frequency search error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while searching frequencies'
            ]);
        }
    }
}
