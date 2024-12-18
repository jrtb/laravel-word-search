@extends('layouts.app')

@section('title', 'Word Search')

@section('content')
    <div class="space-y-6">
        <!-- Pattern Search Section -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Pattern Search</h2>
            <div class="mb-6">
                <div class="mb-6">
                    <h3 class="text-lg font-medium mb-2">List Descriptions</h3>
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium">Omnigrams</h4>
                            <p class="text-gray-600">Words that are 8+ letters long, contain exactly 8 unique letters, and include the letter 'S'.</p>
                        </div>
                        <div>
                            <h4 class="font-medium">Word-Checker Dictionary</h4>
                            <p class="text-gray-600">Words that are 5+ letters long, contain the letter 'S', and have a maximum of 8 unique letters.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <input type="text" id="searchInput" placeholder="Enter search term" 
                        class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    
                    <div class="space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="list" value="omnigrams" checked class="form-radio">
                            <span class="ml-2">Omnigrams</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="list" value="wordchecker" class="form-radio">
                            <span class="ml-2">Word-Checker</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="list" value="both" class="form-radio">
                            <span class="ml-2">Both Lists</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Frequency Search Section -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Frequency Search</h2>
            <div class="space-y-4">
                <div>
                    <label for="frequencyInput" class="block text-sm font-medium text-gray-700 mb-1">
                        Minimum Word Frequency
                    </label>
                    <input type="number" 
                           id="frequencyInput" 
                           step="0.0000000001" 
                           min="0" 
                           value="0.0000009"
                           class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-sm text-gray-500">Current game threshold: 0.0000009 (default value)</p>
                </div>
                <div class="relative">
                    <button id="searchFrequency" 
                            class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        Search by Frequency
                    </button>
                    <div id="loadingIndicator" class="hidden">
                        <div class="absolute inset-0 bg-blue-500 opacity-75 rounded flex items-center justify-center">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="ml-2 text-white">Processing...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div id="results" class="bg-white rounded-lg shadow p-6 hidden">
            <!-- Results will be displayed here -->
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const searchInput = document.getElementById('searchInput');
    const frequencyInput = document.getElementById('frequencyInput');
    const searchFrequencyBtn = document.getElementById('searchFrequency');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const resultsDiv = document.getElementById('results');
    let searchTimeout;

    // Get CSRF token from the meta tag
    function getCsrfToken() {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!token) {
            console.error('CSRF token not found');
            return null;
        }
        return token;
    }

    // Add CSRF token to all fetch requests
    function fetchWithCsrf(url, options = {}) {
        const token = getCsrfToken();
        if (!token) {
            return Promise.reject(new Error('CSRF token not found'));
        }

        const defaultOptions = {
            method: options.method || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        return fetch(url, { ...defaultOptions, ...options })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            });
    }

    function performSearch() {
        const query = searchInput.value;
        const list = document.querySelector('input[name="list"]:checked').value;

        if (query.length < 2) {
            resultsDiv.classList.add('hidden');
            resultsDiv.innerHTML = '';
            return;
        }

        resultsDiv.classList.remove('hidden');
        resultsDiv.innerHTML = '<div class="text-gray-500">Searching...</div>';
        
        fetchWithCsrf('/search', {
            method: 'POST',
            body: JSON.stringify({ query, list })
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Unknown error occurred');
            }
            
            let html = '';
            const results = data.results;
            
            if (Array.isArray(results.omnigrams) && results.omnigrams.length > 0) {
                html += `<div class="mb-4">
                    <h3 class="font-medium mb-2">Omnigrams matches:</h3>
                    <ul class="list-disc pl-5">
                        ${results.omnigrams.map(word => `<li>${word}</li>`).join('')}
                    </ul>
                </div>`;
            }
            
            if (Array.isArray(results.wordchecker) && results.wordchecker.length > 0) {
                html += `<div>
                    <h3 class="font-medium mb-2">Word-Checker matches:</h3>
                    <ul class="list-disc pl-5">
                        ${results.wordchecker.map(word => `<li>${word}</li>`).join('')}
                    </ul>
                </div>`;
            }
            
            if (html) {
                resultsDiv.classList.remove('hidden');
                resultsDiv.innerHTML = html;
            } else {
                resultsDiv.classList.remove('hidden');
                resultsDiv.innerHTML = 'No matches found';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            resultsDiv.classList.remove('hidden');
            resultsDiv.innerHTML = `<div class="text-red-600">Error: ${error.message}</div>`;
        });
    }

    function performFrequencySearch() {
        const frequency = parseFloat(frequencyInput.value);
        if (isNaN(frequency) || frequency < 0) {
            alert('Please enter a valid frequency (must be a positive number)');
            return;
        }

        loadingIndicator.classList.remove('hidden');
        searchFrequencyBtn.disabled = true;
        resultsDiv.classList.add('hidden');

        fetchWithCsrf('/search-frequency', {
            method: 'POST',
            body: JSON.stringify({ frequency })
        })
        .then(data => {
            loadingIndicator.classList.add('hidden');
            searchFrequencyBtn.disabled = false;

            if (!data.success) {
                resultsDiv.classList.remove('hidden');
                resultsDiv.innerHTML = `<div class="text-red-600">Error: ${data.error || 'Unknown error occurred'}</div>`;
                return;
            }

            let html = `<div class="mb-4">
                <h3 class="font-medium mb-2">Words with frequency ≥ ${frequency}:</h3>
                <p class="mb-4">At least ${data.total_count} matching words found</p>`;
            
            if (data.words && data.words.length > 0) {
                html += `<ul class="list-disc pl-5 grid grid-cols-4 gap-2">
                    ${data.words.map(word => `<li>${word}</li>`).join('')}
                </ul>`;
            } else {
                html += '<p>No words found matching the frequency criteria.</p>';
            }
            
            html += '</div>';
            resultsDiv.classList.remove('hidden');
            resultsDiv.innerHTML = html;
        })
        .catch(error => {
            loadingIndicator.classList.add('hidden');
            searchFrequencyBtn.disabled = false;

            console.error('Frequency search error:', error);
            resultsDiv.classList.remove('hidden');
            resultsDiv.innerHTML = `<div class="text-red-600">Error: ${error.message}</div>`;
        });
    }

    // Event listeners
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    });

    document.querySelectorAll('input[name="list"]').forEach(radio => {
        radio.addEventListener('change', performSearch);
    });

    searchFrequencyBtn.addEventListener('click', performFrequencySearch);
</script>
@endpush 