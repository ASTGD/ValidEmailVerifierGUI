@extends('layouts.portal')

@section('content')
<div class="space-y-10">

    <!-- 1. HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">{{ __('Billing & Subscription') }}</h1>
            <p class="text-[#64748B] font-medium mt-1">{{ __('Manage your plan, payment methods, and billing history.') }}</p>
        </div>
        @if (!$isActive)
        <div class="flex items-center gap-2 px-4 py-2 bg-[#FEF3C7] border border-[#F59E0B]/20 rounded-xl">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-[#F59E0B]"></i>
            <span class="text-[10px] font-black text-[#92400E] uppercase tracking-tighter">{{ __('Action Required: No Active Plan') }}</span>
        </div>
        @endif
    </div>

    <!-- 2. STATUS ALERTS -->
    @if (session('status'))
        <div class="bg-[#DCFCE7] border border-[#16A34A]/20 p-4 rounded-2xl flex items-center gap-3">
            <i data-lucide="check-circle" class="text-[#16A34A] w-5 h-5"></i>
            <p class="text-sm font-bold text-[#16A34A]">{{ session('status') }}</p>
        </div>
    @endif

    <!-- 3. SUBSCRIPTION OVERVIEW CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

        <!-- Current Plan Card -->
        <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm flex items-center gap-6 hover:shadow-md transition-shadow">
            <div class="w-14 h-14 bg-[#E9F2FB] text-[#1E7CCF] rounded-2xl flex items-center justify-center shrink-0">
                <i data-lucide="package" class="w-7 h-7"></i>
            </div>
            <div>
                <p class="text-[#64748B] text-[10px] font-black uppercase tracking-widest mb-1">{{ __('Current Plan') }}</p>
                <h3 class="text-2xl font-black text-[#0F172A]">{{ $priceName ?: __('Standard') }}</h3>
                <p class="text-xs text-[#64748B] mt-1 italic">{{ __('Billed based on volume.') }}</p>
            </div>
        </div>

        <!-- Subscription Status Card -->
        <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm flex items-center gap-6 hover:shadow-md transition-shadow">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0
                @if($isActive) bg-[#DCFCE7] text-[#16A34A]
                @elseif($subscription?->onGracePeriod()) bg-[#FEF3C7] text-[#F59E0B]
                @else bg-[#F1F5F9] text-[#64748B] @endif">
                <i data-lucide="shield-check" class="text-white w-7 h-7"></i>
            </div>
            <div>
                <p class="text-[#64748B] text-[10px] font-black uppercase tracking-widest mb-1">{{ __('Account Status') }}</p>
                @if ($isActive)
                    <h3 class="text-2xl font-black text-[#16A34A] flex items-center gap-2">{{ __('Active') }} <span class="w-2 h-2 rounded-full bg-[#16A34A] animate-pulse"></span></h3>
                @elseif ($subscription?->onGracePeriod())
                    <h3 class="text-2xl font-black text-[#F59E0B]">{{ __('Grace Period') }}</h3>
                @else
                    <h3 class="text-2xl font-black text-[#64748B]">{{ __('Inactive') }}</h3>
                @endif
            </div>
        </div>
    </div>

    <!-- 4. ACTION SECTION -->
    <div class="bg-white rounded-[2.5rem] border border-[#E2E8F0] shadow-sm overflow-hidden">
        <div class="p-12 text-center max-w-2xl mx-auto">
            @unless ($isActive)
                <div class="w-20 h-20 bg-[#F1F5F9] rounded-3xl flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="credit-card" class="w-10 h-10 text-[#1E7CCF]"></i>
                </div>
                <h3 class="text-2xl font-black text-[#0F172A] mb-4">{{ __('Ready to scale?') }}</h3>
                <p class="text-[#64748B] mb-10 font-medium">
                    {{ __('Enable billing to unlock high-volume verifications and professional reporting tools.') }}
                </p>
                <form method="POST" action="{{ route('billing.subscribe') }}">
                    @csrf
                    <button type="submit" class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-12 py-4 rounded-xl font-bold text-lg shadow-xl shadow-blue-100 transition-all flex items-center gap-3 mx-auto">
                        <i data-lucide="zap" class="w-5 h-5 text-white"></i> {{ __('Subscribe & Activate Now') }}
                    </button>
                </form>
            @else
                <div class="flex items-center gap-4 p-6 bg-[#F8FAFC] rounded-2xl border border-[#E2E8F0] text-left">
                    <i data-lucide="info" class="text-[#1E7CCF] w-6 h-6 shrink-0"></i>
                    <p class="text-sm text-[#64748B] font-medium leading-relaxed">
                        {{ __('Your payment management and invoices will appear here once your Stripe session is updated.') }}
                    </p>
                </div>
            @endunless
        </div>
    </div>
</div>
@endsection
