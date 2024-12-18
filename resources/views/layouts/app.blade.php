<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Word Search - @yield('title')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="text-xl font-bold text-gray-800">Word Search</span>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="{{ route('word.search') }}" 
                           class="@if(request()->routeIs('word.search')) border-blue-500 text-gray-900 @else border-transparent text-gray-500 @endif
                                  inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Word Search
                        </a>
                        <a href="{{ route('player.stats') }}"
                           class="@if(request()->routeIs('player.stats')) border-blue-500 text-gray-900 @else border-transparent text-gray-500 @endif
                                  inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Player Statistics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html> 