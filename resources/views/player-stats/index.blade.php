@extends('layouts.app')

@section('title', 'Player Statistics')

@section('content')
    <div class="space-y-6">
        <!-- Top 10 Longest Words Section -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Top 10 Longest Words</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Word</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Length</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Player</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody id="topWords" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="5" class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 text-center">
                                Loading top words...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top 10 Words in a Game Section -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Top 10 Words in a Game</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Player</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Words Found</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($topWordCounts as $index => $record)
                            <tr class="{{ $index % 2 ? 'bg-gray-50' : '' }}">
                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #{{ $index + 1 }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                    Player #{{ substr($record->player_id, 0, 8) }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                    {{ $record->highest_word_count }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                    {{ $record->created_at->format('M j, Y') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 text-center">
                                    No records yet. Be the first to find some words!
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Function to fetch and display top 10 longest words
    function fetchTopWords() {
        const topWordsBody = document.getElementById('topWords');
        
        fetch('/api/v1/longest-word/top')
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Failed to fetch top words');
                }

                if (data.words.length === 0) {
                    topWordsBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 text-center">
                                No words submitted yet. Be the first to submit a word!
                            </td>
                        </tr>
                    `;
                    return;
                }

                topWordsBody.innerHTML = data.words.map((word, index) => `
                    <tr class="${index % 2 ? 'bg-gray-50' : ''}">
                        <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                            #${index + 1}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                            ${word.word}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                            ${word.length}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                            Player #${word.player_id.substring(0, 8)}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                            ${new Date(word.submitted_at).toLocaleDateString()}
                        </td>
                    </tr>
                `).join('');
            })
            .catch(error => {
                console.error('Error fetching top words:', error);
                topWordsBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-4 py-2 whitespace-nowrap text-sm text-red-600 text-center">
                            Error loading top words: ${error.message}
                        </td>
                    </tr>
                `;
            });
    }

    // Initial top words fetch
    fetchTopWords();
</script>
@endpush 