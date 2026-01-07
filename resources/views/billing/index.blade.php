@component('layouts.portal')
    <x-portal-layout>
        <x-slot name="header">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">{{ __('Billing') }}</h2>
                <p class="text-sm text-gray-500">{{ __('Manage your subscription and payment details.') }}</p>
            </div>
        </x-slot>

        <div class="space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">{{ __('Plan') }}</p>
                    <p class="mt-2 text-lg font-semibold text-gray-900">{{ $priceName ?: __('Plan') }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">{{ __('Status') }}</p>
                    @if ($isActive)
                        <p class="mt-2 text-sm font-semibold text-green-700">{{ __('Active') }}</p>
                    @elseif ($subscription?->onGracePeriod())
                        <p class="mt-2 text-sm font-semibold text-yellow-700">{{ __('Grace period') }}</p>
                    @else
                        <p class="mt-2 text-sm font-semibold text-gray-700">{{ __('Inactive') }}</p>
                    @endif
                </div>
            </div>

            @unless ($isActive)
                <form method="POST" action="{{ route('billing.subscribe') }}">
                    @csrf
                    <x-primary-button>
                        {{ __('Subscribe') }}
                    </x-primary-button>
                </form>
            @endunless

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                {{ __('Payment management and invoices will appear here once Stripe is configured.') }}
            </div>
        </div>
    </x-portal-layout>
@endcomponent
