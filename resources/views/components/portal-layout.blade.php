<div class="min-h-screen bg-gray-100">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <a href="{{ route('portal.dashboard') }}" class="text-lg font-semibold text-gray-900" wire:navigate>
                        {{ __('Valid Email Verifier') }}
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
            <aside class="lg:w-64 shrink-0">
                <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                    <nav class="space-y-1">
                        <a href="{{ route('portal.dashboard') }}" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                            {{ __('Dashboard') }}
                        </a>
                        <a href="{{ route('portal.upload') }}" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.upload') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                            {{ __('Verify List') }}
                        </a>
                        <a href="{{ route('portal.jobs.index') }}" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.jobs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                            {{ __('Jobs') }}
                        </a>
                        <a href="{{ route('billing.index') }}" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('billing.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
                            {{ __('Billing') }}
                        </a>
                        <a href="{{ route('portal.settings') }}" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.settings') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}" wire:navigate>
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
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>
