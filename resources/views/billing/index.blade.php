<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Billing') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">
                    @if (session('status'))
                        <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="space-y-2">
                        <p class="text-sm text-gray-500">{{ __('Plan') }}</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $priceName ?: __('Plan') }}</p>
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm text-gray-500">{{ __('Status') }}</p>
                        @if ($isActive)
                            <p class="text-sm font-semibold text-green-700">{{ __('Active') }}</p>
                        @elseif ($subscription?->onGracePeriod())
                            <p class="text-sm font-semibold text-yellow-700">{{ __('Grace period') }}</p>
                        @else
                            <p class="text-sm font-semibold text-gray-700">{{ __('Inactive') }}</p>
                        @endif
                    </div>

                    @unless ($isActive)
                        <form method="POST" action="{{ route('billing.subscribe') }}">
                            @csrf
                            <x-primary-button>
                                {{ __('Subscribe') }}
                            </x-primary-button>
                        </form>
                    @endunless
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
