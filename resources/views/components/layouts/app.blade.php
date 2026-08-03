<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Project LevelUp' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white text-ink-900 antialiased">
    <div class="flex h-full">
        @include('layouts.sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            @isset($topbar)
                <header class="flex h-14 flex-none items-center gap-3 border-b border-ink-200 px-6">
                    {{ $topbar }}
                </header>
            @endisset

            @if (session('status'))
                <div class="border-b border-brand-100 bg-brand-50 px-6 py-2 text-sm text-brand-600">
                    {{ session('status') }}
                </div>
            @endif

            <main class="min-h-0 flex-1 overflow-auto">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
