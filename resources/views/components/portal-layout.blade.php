<div class="min-h-screen bg-gray-100" x-data="{ mobileNavOpen: false }">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <button type="button" class="lg:hidden inline-flex items-center justify-center rounded-md border border-gray-200 bg-white p-2 text-gray-500 hover:bg-gray-50" @click="mobileNavOpen = true">
                        <span class="sr-only">{{ __('Open navigation') }}</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <a href="{{ route('portal.dashboard') }}" class="text-lg font-semibold text-gray-900" wire:navigate>
                        {{ config('app.name') }}
                    </a>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">
                        {{ __('Customer Portal') }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('portal.upload') }}" class="hidden sm:inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500" wire:navigate>
                        {{ __('Upload') }}
                    </a>
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <span>{{ auth()->user()->name ?: auth()->user()->email }}</span>
                                <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile')" wire:navigate>
                                {{ __('Profile') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('portal.settings')" wire:navigate>
                                {{ __('Settings') }}
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row items-start gap-8 py-8">
            <aside class="lg:w-64 shrink-0 hidden lg:block">
                <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                    <nav class="space-y-1">
                        <a href="{{ route('portal.dashboard') }}" class="group flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                            <svg class="h-4 w-4 {{ request()->routeIs('portal.dashboard') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M4 10v10a1 1 0 001 1h6m4-11v11a1 1 0 01-1 1h-4" />
                            </svg>
                            {{ __('Dashboard') }}
                        </a>
                        <a href="{{ route('portal.upload') }}" class="group flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.upload') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                            <svg class="h-4 w-4 {{ request()->routeIs('portal.upload') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v4a1 1 0 001 1h14a1 1 0 001-1v-4" />
                            </svg>
                            {{ __('Verify List') }}
                        </a>
                        <a href="{{ route('portal.jobs.index') }}" class="group flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.jobs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                            <svg class="h-4 w-4 {{ request()->routeIs('portal.jobs.*') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                            </svg>
                            {{ __('Jobs') }}
                        </a>
                        <a href="{{ route('billing.index') }}" class="group flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('billing.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                            <svg class="h-4 w-4 {{ request()->routeIs('billing.*') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3-.895-3-2s1.343-2 3-2 3 .895 3 2-1.343 2-3 2zm0 0v12m0 0H7m5 0h5" />
                            </svg>
                            {{ __('Billing') }}
                        </a>
                        <a href="{{ route('portal.settings') }}" class="group flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.settings') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                            <svg class="h-4 w-4 {{ request()->routeIs('portal.settings') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.983 4a1.5 1.5 0 011.52 1.32l.1.8a7.5 7.5 0 012.12.88l.68-.4a1.5 1.5 0 012.04.54l.75 1.3a1.5 1.5 0 01-.54 2.04l-.68.4c.08.34.12.69.12 1.04s-.04.7-.12 1.04l.68.4a1.5 1.5 0 01.54 2.04l-.75 1.3a1.5 1.5 0 01-2.04.54l-.68-.4a7.5 7.5 0 01-2.12.88l-.1.8a1.5 1.5 0 01-1.52 1.32h-1.5a1.5 1.5 0 01-1.52-1.32l-.1-.8a7.5 7.5 0 01-2.12-.88l-.68.4a1.5 1.5 0 01-2.04-.54l-.75-1.3a1.5 1.5 0 01.54-2.04l.68-.4A7.62 7.62 0 014 12c0-.35.04-.7.12-1.04l-.68-.4a1.5 1.5 0 01-.54-2.04l.75-1.3a1.5 1.5 0 012.04-.54l.68.4c.66-.38 1.37-.68 2.12-.88l.1-.8A1.5 1.5 0 019.483 4h1.5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                            </svg>
                            {{ __('Settings') }}
                        </a>
                    </nav>
                </div>
            </aside>

            <main class="flex-1 min-w-0">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    @isset($header)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-5">
                            <div>
                                {{ $header }}
                            </div>
                            @isset($headerAction)
                                <div>
                                    {{ $headerAction }}
                                </div>
                            @endisset
                        </div>
                    @endisset

                    <div class="px-6 py-6">
                        @if (session('status'))
                            <x-flash type="success" :message="session('status')" />
                        @endif
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div
        x-show="mobileNavOpen"
        x-cloak
        class="fixed inset-0 z-40 bg-black/40 lg:hidden"
        @click="mobileNavOpen = false"
    ></div>
    <div
        x-show="mobileNavOpen"
        x-cloak
        class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-xl lg:hidden"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
    >
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-4">
            <div class="text-sm font-semibold text-gray-900">{{ config('app.name') }}</div>
            <button type="button" class="rounded-md p-2 text-gray-500 hover:bg-gray-100" @click="mobileNavOpen = false">
                <span class="sr-only">{{ __('Close navigation') }}</span>
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <nav class="space-y-1 px-4 py-4">
            <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                {{ __('Dashboard') }}
            </a>
            <a href="{{ route('portal.upload') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.upload') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                {{ __('Verify List') }}
            </a>
            <a href="{{ route('portal.jobs.index') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.jobs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                {{ __('Jobs') }}
            </a>
            <a href="{{ route('billing.index') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('billing.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                {{ __('Billing') }}
            </a>
            <a href="{{ route('portal.settings') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.settings') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                {{ __('Settings') }}
            </a>
            <a href="{{ route('portal.support') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.support') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                {{ __('Support') }}
            </a>
        </nav>
        <div class="border-t border-gray-200 px-4 py-4">
            <div class="text-xs text-gray-500">{{ auth()->user()->email }}</div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="w-full rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white">
                    {{ __('Log Out') }}
                </button>
            </form>
        </div>
    </div>
</div>
