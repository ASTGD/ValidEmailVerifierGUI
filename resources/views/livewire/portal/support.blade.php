<x-portal-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Support') }}</h2>
            <p class="text-sm text-gray-500">{{ __('Get help with uploads, billing, or verification results.') }}</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="rounded-lg border border-gray-200 p-4">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('Contact support') }}</h3>
            <p class="mt-2 text-sm text-gray-600">
                {{ __('Include the job ID and filename so we can assist quickly.') }}
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-sm text-gray-500">{{ __('Email') }}</p>
                @if($supportEmail)
                    <a href="mailto:{{ $supportEmail }}" class="mt-2 inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-500">
                        {{ $supportEmail }}
                    </a>
                @else
                    <p class="mt-2 text-sm text-gray-500">{{ __('Support email not configured yet.') }}</p>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-sm text-gray-500">{{ __('Support portal') }}</p>
                @if($supportUrl)
                    <a href="{{ $supportUrl }}" class="mt-2 inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-500" target="_blank" rel="noopener">
                        {{ __('Open support portal') }}
                    </a>
                @else
                    <p class="mt-2 text-sm text-gray-500">{{ __('Support portal not configured yet.') }}</p>
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
            <h4 class="text-sm font-semibold text-gray-900">{{ __('CSV format checklist') }}</h4>
            <ul class="mt-2 list-disc list-inside text-sm text-gray-600">
                <li>{{ __('One email per line') }}</li>
                <li>{{ __('UTF-8 encoded file') }}</li>
                <li>{{ __('Header row optional') }}</li>
            </ul>
        </div>
    </div>
</x-portal-layout>
