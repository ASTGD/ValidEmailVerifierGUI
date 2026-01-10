<div class="space-y-10">
    <!-- HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">{{ __('Support Center') }}</h1>
            <p class="text-[#64748B] font-medium mt-1">
                {{ __('Get help with uploads, billing, or verification results.') }}</p>
        </div>
    </div>

    <!-- CONTACT HELP CARD -->
    <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm flex items-center gap-6">
        <div class="w-14 h-14 bg-[#E9F2FB] text-[#1E7CCF] rounded-2xl flex items-center justify-center shrink-0">
            <i data-lucide="message-square" class="w-7 h-7"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-[#0F172A]">{{ __('Direct Assistance') }}</h3>
            <p class="text-sm text-[#64748B] mt-1">
                {{ __('Please include your Job ID and filename when contacting us so we can assist you faster.') }}
            </p>
        </div>
    </div>

    <!-- SUPPORT CHANNELS GRID -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Email Support -->
        <div
            class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm group hover:border-[#1E7CCF] transition-all">
            <p class="text-[#64748B] text-xs font-bold uppercase tracking-widest mb-4">{{ __('Email Support') }}</p>
            @if ($supportEmail)
                <div class="flex items-center gap-3">
                    <i data-lucide="mail" class="text-[#1E7CCF] w-5 h-5"></i>
                    <a href="mailto:{{ $supportEmail }}"
                        class="text-xl font-black text-[#0F172A] hover:text-[#1E7CCF] transition-colors">
                        {{ $supportEmail }}
                    </a>
                </div>
            @else
                <p class="text-sm font-bold text-[#94A3B8] italic">{{ __('Support email not configured.') }}</p>
            @endif
        </div>

        <!-- Support Portal -->
        <div
            class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm group hover:border-[#1E7CCF] transition-all text-left">
            <p class="text-[#64748B] text-xs font-bold uppercase tracking-widest mb-4">{{ __('Knowledge Base') }}
            </p>
            @if ($supportUrl)
                <a href="{{ $supportUrl }}" target="_blank" rel="noopener"
                    class="inline-flex items-center gap-3 text-xl font-black text-[#0F172A] hover:text-[#1E7CCF] transition-colors">
                    {{ __('Open support portal') }} <i data-lucide="external-link" class="w-5 h-5"></i>
                </a>
            @else
                <p class="text-sm font-bold text-[#94A3B8] italic">{{ __('Support portal not configured.') }}</p>
            @endif
        </div>
    </div>

    <!-- CHECKLIST SECTION -->
    <div class="bg-[#F1F5F9] rounded-[2.5rem] border border-[#E2E8F0] p-10">
        <div class="flex items-center gap-3 mb-8">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-[#1E7CCF] shadow-sm">
                <i data-lucide="clipboard-check" class="w-6 h-6"></i>
            </div>
            <h4 class="text-xl font-black text-[#0F172A]">{{ __('CSV Format Checklist') }}</h4>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl border border-white shadow-sm flex items-center gap-4">
                <i data-lucide="check-circle-2" class="text-[#16A34A] w-5 h-5"></i>
                <span class="font-bold text-[#334155] text-sm">{{ __('One email per line') }}</span>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-white shadow-sm flex items-center gap-4">
                <i data-lucide="check-circle-2" class="text-[#16A34A] w-5 h-5"></i>
                <span class="font-bold text-[#334155] text-sm">{{ __('UTF-8 encoded file') }}</span>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-white shadow-sm flex items-center gap-4">
                <i data-lucide="check-circle-2" class="text-[#16A34A] w-5 h-5"></i>
                <span class="font-bold text-[#334155] text-sm">{{ __('Header row optional') }}</span>
            </div>
        </div>
    </div>
</div>



{{-- <x-portal-layout>
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
                @if ($supportEmail)
                    <a href="mailto:{{ $supportEmail }}" class="mt-2 inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-500">
                        {{ $supportEmail }}
                    </a>
                @else
                    <p class="mt-2 text-sm text-gray-500">{{ __('Support email not configured yet.') }}</p>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-sm text-gray-500">{{ __('Support portal') }}</p>
                @if ($supportUrl)
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
</x-portal-layout> --}}
