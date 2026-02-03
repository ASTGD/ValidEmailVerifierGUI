<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased bg-[#F8FAFC] text-[#0F172A]" x-data="{ mobileNavOpen: false, sidebarOpen: true }">
    <div class="flex min-h-screen">
        <aside x-show="sidebarOpen" x-cloak
            class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-[#E2E8F0] bg-white md:flex">
            <div class="p-6 flex items-center gap-3">
                <div
                    class="w-10 h-10 bg-[#1E7CCF] rounded-xl flex items-center justify-center shadow-lg shadow-blue-200">
                    <i data-lucide="shield-check" class="text-white w-6 h-6"></i>
                </div>
                <span class="text-lg font-black text-[#0F172A] tracking-tight">{{ config('app.name') }}</span>
            </div>
            <nav class="flex-1 px-4 space-y-1 mt-2">
                <a href="{{ route('portal.dashboard') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-colors {{ request()->routeIs('portal.dashboard') ? 'bg-[#E9F2FB] text-[#1E7CCF] border-r-4 border-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                    wire:navigate>
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i> {{ __('Dashboard') }}
                </a>
                <!-- VERIFICATION SECTION -->
                <div class="px-4 mt-8 mb-2 text-[10px] font-black text-[#94A3B8] uppercase tracking-[0.15em]">
                    {{ __('Verification') }}</div>
                <a href="{{ route('portal.upload') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-colors {{ request()->routeIs('portal.upload') ? 'bg-[#E9F2FB] text-[#1E7CCF] border-r-4 border-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                    wire:navigate>
                    <i data-lucide="upload-cloud" class="w-5 h-5"></i> {{ __('Verify List') }}
                </a>
                <a href="{{ route('portal.single-check') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-colors {{ request()->routeIs('portal.single-check') ? 'bg-[#E9F2FB] text-[#1E7CCF] border-r-4 border-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                    wire:navigate>
                    <i data-lucide="mail-check" class="w-5 h-5"></i> {{ __('Single Check') }}
                </a>
                <a href="{{ route('portal.jobs.index') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-colors {{ request()->routeIs('portal.jobs.*') ? 'bg-[#E9F2FB] text-[#1E7CCF] border-r-4 border-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                    wire:navigate>
                    <i data-lucide="list-checks" class="w-5 h-5"></i> {{ __('Jobs') }}
                </a>
                <!-- STATS SECTION -->
                <div class="px-4 mt-8 mb-2 text-[10px] font-black text-[#94A3B8] uppercase tracking-[0.15em]">
                    {{ __('Stats') }}</div>
                <a href="{{ route('portal.orders.index') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-colors {{ request()->routeIs('portal.orders.*') ? 'bg-[#E9F2FB] text-[#1E7CCF] border-r-4 border-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                    wire:navigate>
                    <i data-lucide="receipt" class="w-5 h-5"></i> {{ __('My Orders') }}
                </a>
                <a href="{{ route('billing.index') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-colors {{ request()->routeIs('billing.*') ? 'bg-[#E9F2FB] text-[#1E7CCF] border-r-4 border-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                    wire:navigate>
                    <i data-lucide="credit-card" class="w-5 h-5"></i> {{ __('Billing') }}
                </a>
                <!-- SYSTEM SECTION -->
                <div class="px-4 mt-8 mb-2 text-[10px] font-black text-[#94A3B8] uppercase tracking-[0.15em]">
                    {{ __('System') }}</div>
                <a href="{{ route('portal.settings') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-colors {{ request()->routeIs('portal.settings') ? 'bg-[#E9F2FB] text-[#1E7CCF] border-r-4 border-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                    wire:navigate>
                    <i data-lucide="settings" class="w-5 h-5"></i> {{ __('Settings') }}
                </a>
                <a href="{{ route('portal.support') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-colors {{ request()->routeIs('portal.support') ? 'bg-[#E9F2FB] text-[#1E7CCF] border-r-4 border-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                    wire:navigate>
                    <i data-lucide="help-circle" class="w-5 h-5"></i> {{ __('Support') }}
                </a>
            </nav>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 transition-[margin] duration-200"
            :class="sidebarOpen ? 'md:ml-72' : 'md:ml-0'">
            <header class="h-16 md:h-20 bg-white border-b border-[#E2E8F0] sticky top-0 z-30">
                <div class="h-full px-4 md:px-8 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button type="button"
                            class="md:hidden inline-flex items-center justify-center rounded-md border border-[#E2E8F0] bg-white p-2 text-[#64748B] hover:bg-[#F8FAFC]"
                            @click="mobileNavOpen = true">
                            <span class="sr-only">{{ __('Open navigation') }}</span>
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <button type="button"
                            class="hidden md:inline-flex items-center justify-center rounded-md border border-[#E2E8F0] bg-white p-2 text-[#64748B] hover:bg-[#F8FAFC]"
                            @click="sidebarOpen = !sidebarOpen">
                            <span class="sr-only">{{ __('Toggle sidebar') }}</span>
                            <i data-lucide="chevrons-left" class="w-5 h-5" x-show="sidebarOpen"></i>
                            <i data-lucide="chevrons-right" class="w-5 h-5" x-show="!sidebarOpen" x-cloak></i>
                        </button>
                        <span
                            class="text-sm font-black uppercase tracking-widest text-[#64748B]">{{ __('Customer Portal') }}</span>
                    </div>

                    <div class="flex items-center gap-4">
                        <!-- NOTIFICATION BELL -->
                        <button
                            class="relative text-[#64748B] hover:text-[#1E7CCF] transition-colors p-2 rounded-full hover:bg-[#F8FAFC]">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                            <span
                                class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
                        </button>
                        {{-- <a href="{{ route('portal.upload') }}"
                            class="hidden sm:inline-flex items-center rounded-md bg-[#1E7CCF] px-4 py-2 text-xs font-black uppercase tracking-widest text-white hover:bg-[#1866AD]"
                            wire:navigate>
                            {{ __('Upload') }}
                        </a> --}}
                        <!-- AVAILABLE BALANCE WIDGET -->
                        <div
                            class="hidden lg:flex items-center bg-[#1E7CCF] text-white px-4 py-2 rounded-lg gap-4 shadow-sm">
                            <i data-lucide="wallet" class="w-5 h-5 opacity-80"></i>
                            <div class="flex flex-col leading-none">
                                <span
                                    class="text-[9px] font-black tracking-widest opacity-70">{{ __('Available Credits') }}</span>
                                <span class="text-sm font-black">{{ __('100 USD') }}</span>
                            </div>
                            <a href="{{ route('billing.index') }}"
                                class="bg-white/20 p-1 rounded hover:bg-white/30 transition-colors">
                                <i data-lucide="plus" class="w-3 h-3 text-white"></i>
                            </a>
                        </div>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false"
                                class="flex items-center gap-3 focus:outline-none">
                                <div class="text-right hidden sm:block">
                                    <p class="text-sm font-bold text-[#0F172A]">
                                        {{ auth()->user()->name ?: auth()->user()->email }}</p>
                                    <p class="text-[10px] font-bold text-[#94A3B8] uppercase tracking-tighter">
                                        {{ __('Verified Member') }}</p>
                                </div>
                                <div
                                    class="w-10 h-10 bg-[#E9F2FB] rounded-xl flex items-center justify-center text-[#1E7CCF] font-bold border border-[#1E7CCF]/10">
                                    {{ substr(auth()->user()->name ?: auth()->user()->email, 0, 1) }}
                                </div>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-[#64748B]"
                                    :class="open ? 'rotate-180' : ''"></i>
                            </button>

                            <div x-show="open" x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute right-0 mt-3 w-48 bg-white rounded-2xl shadow-xl border border-[#E2E8F0] py-2 z-50"
                                style="display: none;">
                                <a href="{{ route('profile') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold text-[#334155] hover:bg-[#F8FAFC] hover:text-[#1E7CCF] transition-colors"
                                    wire:navigate>
                                    <i data-lucide="user" class="w-4 h-4"></i> {{ __('My Profile') }}
                                </a>
                                <a href="{{ route('portal.settings') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold text-[#334155] hover:bg-[#F8FAFC] hover:text-[#1E7CCF] transition-colors"
                                    wire:navigate>
                                    <i data-lucide="settings" class="w-4 h-4"></i> {{ __('Settings') }}
                                </a>
                                <div class="border-t border-[#F1F5F9] my-1"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm font-bold text-red-500 hover:bg-red-50 transition-colors">
                                        <i data-lucide="log-out" class="w-4 h-4"></i> {{ __('Logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6 md:p-10">
                <div class="max-w-[1440px] mx-auto">
                    @yield('content', $slot ?? '')
                </div>
            </main>
        </div>
    </div>

    <div x-show="mobileNavOpen" x-cloak class="fixed inset-0 z-40 bg-black/40 md:hidden"
        @click="mobileNavOpen = false"></div>
    <div x-show="mobileNavOpen" x-cloak
        class="fixed inset-y-0 left-0 z-50 w-72 bg-white text-[#0F172A] shadow-xl md:hidden"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
        <div class="flex items-center justify-between border-b border-[#E2E8F0] px-4 py-4">
            <div class="text-sm font-semibold">{{ config('app.name') }}</div>
            <button type="button" class="rounded-md p-2 text-[#64748B] hover:bg-[#F8FAFC]"
                @click="mobileNavOpen = false">
                <span class="sr-only">{{ __('Close navigation') }}</span>
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <nav class="space-y-1 px-4 py-4">
            <a href="{{ route('portal.dashboard') }}"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.dashboard') ? 'bg-[#E9F2FB] text-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                wire:navigate>
                {{ __('Dashboard') }}
            </a>
            <a href="{{ route('portal.upload') }}"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.upload') ? 'bg-[#E9F2FB] text-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                wire:navigate>
                {{ __('Verify List') }}
            </a>
            <a href="{{ route('portal.single-check') }}"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.single-check') ? 'bg-[#E9F2FB] text-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                wire:navigate>
                {{ __('Single Check') }}
            </a>
            <a href="{{ route('portal.jobs.index') }}"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.jobs.*') ? 'bg-[#E9F2FB] text-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                wire:navigate>
                {{ __('Jobs') }}
            </a>
            <a href="{{ route('portal.orders.index') }}"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.orders.*') ? 'bg-[#E9F2FB] text-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                wire:navigate>
                {{ __('My Orders') }}
            </a>
            <a href="{{ route('billing.index') }}"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('billing.*') ? 'bg-[#E9F2FB] text-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                wire:navigate>
                {{ __('Billing') }}
            </a>
            <a href="{{ route('portal.settings') }}"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.settings') ? 'bg-[#E9F2FB] text-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                wire:navigate>
                {{ __('Settings') }}
            </a>
            <a href="{{ route('portal.support') }}"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.support') ? 'bg-[#E9F2FB] text-[#1E7CCF]' : 'text-[#334155] hover:bg-[#F8FAFC]' }}"
                wire:navigate>
                {{ __('Support') }}
            </a>
        </nav>
        <div class="border-t border-[#E2E8F0] px-4 py-4">
            <div class="text-xs text-[#64748B]">{{ auth()->user()->email }}</div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit"
                    class="w-full rounded-md bg-[#1E7CCF] px-3 py-2 text-sm font-semibold text-white hover:bg-[#1866AD]">
                    {{ __('Log Out') }}
                </button>
            </form>
        </div>
    </div>

    @livewireScripts
    <script>
        const initLucide = () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        };

        document.addEventListener('DOMContentLoaded', initLucide);
        document.addEventListener('livewire:init', initLucide);
        document.addEventListener('livewire:navigated', initLucide);
    </script>
</body>

</html>
