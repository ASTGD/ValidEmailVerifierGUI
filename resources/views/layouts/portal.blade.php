{{-- <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Portal | ValidEmail</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CDN (Ensures design works even if Vite is off) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F8FAFC;
            margin: 0;
            padding: 0;
        }

        .sidebar-link-active {
            background-color: #E9F2FB !important;
            color: #1E7CCF !important;
            border-right: 4px solid #1E7CCF;
        }

        /* Fixed Header Fix */
        .nav-sticky {
            background-color: #ffffff !important;
            z-index: 50;
            border-bottom: 1px solid #E2E8F0;
        }
    </style>

    @livewireStyles
</head>

<body class="antialiased flex min-h-screen">

    <!-- SIDEBAR (Left) -->
    <aside class="w-64 bg-white border-r border-[#E2E8F0] fixed h-full z-50 hidden md:flex flex-col">
        <div class="p-6 flex items-center gap-3">
            <div class="w-8 h-8 bg-[#1E7CCF] rounded-lg flex items-center justify-center shadow-lg shadow-blue-200">
                <i data-lucide="shield-check" class="text-white w-5 h-5"></i>
            </div>
            <span class="text-lg font-black text-[#0F172A]">ValidEmail</span>
        </div>

        <nav class="flex-1 px-4 space-y-1 mt-4">
            <a href="{{ route('portal.dashboard') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-[#334155] hover:bg-[#F8FAFC] {{ request()->routeIs('portal.dashboard') ? 'sidebar-link-active' : '' }}">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
            </a>
            <a href="{{ route('portal.upload') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-[#334155] hover:bg-[#F8FAFC] {{ request()->routeIs('portal.upload') ? 'sidebar-link-active' : '' }}">
                <i data-lucide="upload-cloud" class="w-5 h-5"></i> Verify List
            </a>
            <a href="{{ route('portal.jobs.index') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-[#334155] hover:bg-[#F8FAFC] {{ request()->routeIs('portal.jobs.*') ? 'sidebar-link-active' : '' }}">
                <i data-lucide="list-checks" class="w-5 h-5"></i> Jobs
            </a>
            <a href="{{ route('billing.index') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-[#334155] hover:bg-[#F8FAFC] {{ request()->routeIs('billing.*') ? 'sidebar-link-active' : '' }}">
                <i data-lucide="credit-card" class="w-5 h-5"></i> Billing
            </a>
            <a href="{{ route('portal.settings') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-[#334155] hover:bg-[#F8FAFC] {{ request()->routeIs('portal.settings') ? 'sidebar-link-active' : '' }}">
                <i data-lucide="settings" class="w-5 h-5"></i> Settings
            </a>
        </nav>
    </aside>

    <!-- MAIN AREA (Right) -->
    <div class="flex-1 flex flex-col md:ml-64 min-w-0">

        <!-- TOP NAVIGATION -->
        <header class="h-20 nav-sticky px-8 flex items-center justify-between sticky top-0">
            <h2 class="text-xs font-black text-[#64748B] uppercase tracking-widest">Customer Portal</h2>

            <div class="flex items-center gap-6">
                <!-- User Profile & Logout -->
                <div class="flex items-center gap-4 pl-6 border-l border-[#E2E8F0]">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-[#0F172A]">{{ Auth::user()->name }}</p>
                        <!-- LOGOUT FORM -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="text-[11px] font-black text-red-500 hover:underline uppercase tracking-tighter">
                                {{ __('Logout') }}
                            </button>
                        </form>
                    </div>
                    <div
                        class="w-10 h-10 bg-[#E9F2FB] rounded-full flex items-center justify-center text-[#1E7CCF] font-bold border border-[#1E7CCF]/10">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT -->
        <main class="p-8 md:p-12">
            <div class="max-w-[1280px] mx-auto">
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
    <script>
        lucide.createIcons();
    </script>
</body>

</html> --}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'ValidEmail Portal') }}</title>

    <!-- Use Inter Font for a more modern SaaS look -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Lucide Icons for clean UI -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Scripts & Styles (Keeping your original Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Keep your custom style.css for the color variables -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">

    <!-- Tailwind CDN for the new design components -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F8FAFC;
        }

        .sidebar-link-active {
            background-color: #E9F2FB;
            color: #1E7CCF;
            border-right: 4px solid #1E7CCF;
        }
    </style>
</head>

<body class="antialiased flex min-h-screen">

    <!-- 1. SIDEBAR (Fixed left side) -->
    <aside class="w-72 bg-white border-r border-[#E2E8F0] fixed h-full z-50 hidden md:flex flex-col">
        <!-- Brand Logo -->
        <div class="p-8 flex items-center gap-3">
            <div class="w-10 h-10 bg-[#1E7CCF] rounded-xl flex items-center justify-center shadow-lg shadow-blue-200">
                <i data-lucide="shield-check" class="text-white w-6 h-6"></i>
            </div>
            <span class="text-xl font-black text-[#0F172A] tracking-tight">ValidEmail</span>
        </div>

        <!-- Navigation Links (Matching your folder structure) -->
        <nav class="flex-1 px-4 space-y-1 mt-4">
            <a href="/portal/dashboard"
                class="flex items-center gap-3 px-6 py-3.5 rounded-xl font-bold text-[#334155] hover:bg-[#F8FAFC] transition-all">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
            </a>
            <a href="/portal/upload"
                class="flex items-center gap-3 px-6 py-3.5 rounded-xl font-bold text-[#334155] hover:bg-[#F8FAFC] transition-all">
                <i data-lucide="upload-cloud" class="w-5 h-5"></i> Verify New List
            </a>
            <a href="/portal/jobs"
                class="flex items-center gap-3 px-6 py-3.5 rounded-xl font-bold text-[#334155] hover:bg-[#F8FAFC] transition-all">
                <i data-lucide="list-checks" class="w-5 h-5"></i> My Jobs
            </a>
            <a href="/billing"
                class="flex items-center gap-3 px-6 py-3.5 rounded-xl font-bold text-[#334155] hover:bg-[#F8FAFC] transition-all">
                <i data-lucide="credit-card" class="w-5 h-5"></i> Billing
            </a>
            <a href="/portal/support"
                class="flex items-center gap-3 px-6 py-3.5 rounded-xl font-bold text-[#334155] hover:bg-[#F8FAFC] transition-all">
                <i data-lucide="help-circle" class="w-5 h-5"></i> Support
            </a>
        </nav>

        <!-- Bottom Profile Section -->
        <div class="p-6 border-t border-[#E2E8F0]">
            <a href="/portal/settings"
                class="flex items-center gap-3 px-4 py-3 text-[#64748B] hover:text-[#0F172A] transition-colors font-semibold">
                <i data-lucide="settings" class="w-5 h-5"></i> Settings
            </a>
        </div>
    </aside>

    <!-- 2. MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col md:ml-72 min-w-0">

        <!-- TOP HEADER -->
        <header
            class="h-20 bg-white border-b border-[#E2E8F0] px-8 flex items-center justify-between sticky top-0 z-40">
            <div>
                <!-- This can dynamic based on the page -->
                <h2 class="text-sm font-bold text-[#64748B] uppercase tracking-widest">Customer Portal</h2>
            </div>

            <div class="flex items-center gap-6">
                <!-- User Profile & Logout -->
                <div class="flex items-center gap-4 pl-6 border-l border-[#E2E8F0]">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-[#0F172A]">{{ Auth::user()->name }}</p>
                        <!-- LOGOUT FORM -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="text-[11px] font-black text-red-500 hover:underline uppercase tracking-tighter">
                                {{ __('Logout') }}
                            </button>
                        </form>
                    </div>
                    <div
                        class="w-10 h-10 bg-[#E9F2FB] rounded-full flex items-center justify-center text-[#1E7CCF] font-bold border border-[#1E7CCF]/10">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                </div>
            </div>
        </header>

        <!-- 3. DYNAMIC CONTENT (THE SLOT) -->
        <!-- Max-width 1440px as per industry standard for Dashboards -->
        <main class="flex-1 p-6 md:p-10">
            <div class="max-w-[1440px] mx-auto">
                @yield('content', $slot ?? '')
            </div>
        </main>

    </div>

    <script>
        // Initialize Icons
        lucide.createIcons();

        // Auto-highlight active link based on URL
        const currentPath = window.location.pathname;
        document.querySelectorAll('nav a').forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('sidebar-link-active');
                link.classList.remove('text-[#334155]');
            }
        });
    </script>
</body>

</html>

{{-- <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900">
        {{ $slot }}
    </body>
</html> --}}
