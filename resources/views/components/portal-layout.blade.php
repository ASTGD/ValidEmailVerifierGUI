<div class="min-h-screen bg-gray-100">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
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
                    <div class="text-sm text-gray-600">
                        {{ auth()->user()->name ?: auth()->user()->email }}
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row gap-6 py-8">
            <aside class="lg:w-64 shrink-0">
                <nav class="space-y-1">
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
                </nav>
            </aside>

            <main class="flex-1">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    @isset($header)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4 mb-6">
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

                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</div>
