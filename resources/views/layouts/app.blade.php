<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Welfare Election') — Asesewa Government Hospital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant:ital,wght@0,500;0,600;0,700;1,500&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    @stack('head')
</head>
<body class="min-h-dvh antialiased">
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header no-print">
        <div class="gold-line"></div>
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-5">
            <a href="{{ url('/') }}" class="no-underline">
                <p class="kicker">Asesewa Government Hospital</p>
                <p class="display mt-1 text-3xl font-semibold text-[#fffcf7]">Welfare Election 2026</p>
            </a>
            <nav class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm" aria-label="Primary">
                @auth
                    <a class="nav-link" href="{{ route('ec.dashboard') }}">Results</a>
                    <a class="nav-link" href="{{ route('ec.ballot') }}">Ballot</a>
                    <a class="nav-link" href="{{ route('ec.ballot.print') }}">Print</a>
                    <a class="nav-link" href="{{ route('ec.voters') }}">Voters</a>
                    <a class="nav-link" href="{{ route('ec.serials') }}">Serials</a>
                    <a class="nav-link" href="{{ route('ec.tally') }}">Paper count</a>
                    <a class="nav-link" href="{{ route('ec.settings') }}">Election</a>
                    <a class="nav-link" href="{{ route('ec.audit') }}">Audit</a>
                    <a
                        href="{{ route('ec.profile') }}"
                        class="nav-account inline-flex items-center gap-2 rounded-full border border-[color-mix(in_srgb,var(--gold)_45%,transparent)] bg-[color-mix(in_srgb,var(--forest)_55%,#000)] px-3 py-1.5 text-[#fffcf7] no-underline transition hover:border-[var(--gold)]"
                        title="Change your password"
                    >
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[var(--gold)] text-[11px] font-semibold text-[#07140f]" aria-hidden="true">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <span class="leading-tight">
                            <span class="block text-[11px] font-medium tracking-wide">{{ auth()->user()->name }}</span>
                            <span class="block text-[10px] uppercase tracking-[0.14em] text-[color-mix(in_srgb,var(--gold)_85%,white)]">Change password</span>
                        </span>
                    </a>
                    <form method="POST" action="{{ route('ec.logout') }}">@csrf<button class="nav-link">Sign out</button></form>
                @else
                    <a class="nav-link" href="{{ route('results.public') }}">Official results</a>
                    <a class="nav-link" href="{{ route('ec.login') }}">Electoral Commission</a>
                @endauth
            </nav>
        </div>
    </header>
    <main id="main" class="mx-auto max-w-6xl px-4 py-12">
        @if (session('status'))
            <p class="mb-6 rounded-2xl border border-[color-mix(in_srgb,var(--gold)_30%,transparent)] bg-[var(--paper)] px-4 py-3 text-sm text-[var(--forest)]" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->has('election') || $errors->has('ballot') || $errors->has('voters'))
            <p class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first('election') ?: $errors->first('ballot') ?: $errors->first('voters') }}</p>
        @endif
        @yield('content')
    </main>
</body>
</html>
