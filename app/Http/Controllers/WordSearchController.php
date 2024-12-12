<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class WordSearchController extends Controller
{
    public function index()
    {
        return view('word-search');
    }

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
                $list1Content = $s3->get($list1Path);
                $list2Content = $s3->get($list2Path);
            } catch (\Exception $e) {
                Log::error('Failed to read word lists: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to read word lists'
                ]);
            }

            // Convert content to arrays and clean up any empty lines
            $list1Words = array_filter(explode("\n", $list1Content), 'trim');
            $list2Words = array_filter(explode("\n", $list2Content), 'trim');

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
