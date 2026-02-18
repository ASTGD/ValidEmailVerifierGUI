<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Secure Checkout') }} | {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="//unpkg.com/alpinejs" defer></script>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
@php
    $currencyPrefix = strtolower($intent->currency) === 'usd' ? '$' : strtoupper($intent->currency) . ' ';
@endphp

<body class="bg-[#F8FAFC]" x-data="{ 
    useCredit: false, 
    totalCents: {{ $intent->amount_cents }}, 
    creditCents: {{ $totals['available_credit'] ?? 0 }},
    currencyPrefix: '{{ $currencyPrefix }}',
    get payNowCents() {
        if (!this.useCredit) return this.totalCents;
        // Logic must match backend: applied is min(total, available)
        // So payNow is total - min(total, available)
        let applied = Math.min(this.totalCents, this.creditCents);
        return this.totalCents - applied;
    },
    get creditAppliedCents() {
        if (!this.useCredit) return 0;
        return Math.min(this.totalCents, this.creditCents);
    },
    formatMoney(cents) {
        return (cents / 100).toFixed(2);
    }
}">

    <div class="max-w-[1280px] mx-auto px-10 py-20">
        <div class="grid lg:grid-cols-3 gap-12">

            <!-- Left: Order Details (2 Columns) -->
            <div class="lg:col-span-2 space-y-8">
                <h1 class="text-3xl font-black text-[#0F172A]">
                    {{ $intent->type === 'credit' ? __('Add Funds to Balance') : ($intent->type === 'invoice' ? __('Complete Payment') : __('Complete Your Order')) }}
                </h1>

                <!-- 1. Review Details -->
                <div class="bg-white p-8 rounded-3xl border border-[#E2E8F0] shadow-sm">
                    <h3 class="text-lg font-bold mb-6 flex items-center gap-2">
                        <span
                            class="w-8 h-8 bg-[#E9F2FB] text-[#1E7CCF] rounded-full flex items-center justify-center text-sm">1</span>
                        {{ $intent->type === 'credit' ? __('Review Deposit Details') : ($intent->type === 'invoice' ? __('Review Payment Details') : __('Review Order Details')) }}
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if ($intent->type === 'credit')
                            <div class="p-4 bg-[#F8FAFC] rounded-2xl border border-[#E2E8F0]">
                                <p class="text-xs font-bold text-[#64748B] uppercase">{{ __('Deposit Type') }}</p>
                                <p class="text-2xl font-black text-[#0F172A]">{{ __('Credit Balance') }}</p>
                                <p class="text-xs text-[#94A3B8] mt-2">
                                    {{ __('Funds will be added to your account balance.') }}
                                </p>
                            </div>
                        @elseif ($intent->type === 'invoice')
                            <div class="p-4 bg-[#F8FAFC] rounded-2xl border border-[#E2E8F0]">
                                <p class="text-xs font-bold text-[#64748B] uppercase">{{ __('Payment For') }}</p>
                                <p class="text-2xl font-black text-[#0F172A]">
                                    {{ __('Invoice #') }}{{ $intent->invoice?->invoice_number }}</p>
                                <p class="text-xs text-[#94A3B8] mt-2">
                                    {{ __('Payment for outstanding invoice balance.') }}
                                </p>
                            </div>
                        @else
                            <div class="p-4 bg-[#F8FAFC] rounded-2xl border border-[#E2E8F0]">
                                <p class="text-xs font-bold text-[#64748B] uppercase">{{ __('Emails to Verify') }}</p>
                                <p class="text-2xl font-black text-[#0F172A]">{{ number_format($intent->email_count) }}</p>
                                <p class="text-xs text-[#94A3B8] mt-2">{{ $intent->original_filename }}</p>
                            </div>
                        @endif
                        <div class="p-4 bg-[#E9F2FB] rounded-2xl border border-[#1E7CCF]/10">
                            <p class="text-xs font-bold text-[#1E7CCF] uppercase">{{ __('Total Price') }}</p>
                            <p class="text-2xl font-black text-[#1E7CCF]">{{ $currencyPrefix }}{{ $formattedTotal }}</p>
                            <p class="text-xs text-[#1E7CCF] mt-2 font-semibold">
                                {{ $intent->type === 'credit' ? __('Funds Deposit') : ($intent->type === 'invoice' ? __('Invoice Payment') : ($intent->pricingPlan?->name ?? __('Pay-as-you-go'))) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 2. Auth/Payment Logic -->
                <div class="bg-white p-8 rounded-3xl border border-[#E2E8F0] shadow-sm">
                    @if (session('status'))
                        <div
                            class="mb-6 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-5 py-3 text-sm font-semibold text-[#334155]">
                            {{ session('status') }}
                        </div>
                    @endif

                    @guest
                        <h3 class="text-lg font-bold mb-4">{{ __('Account Required') }}</h3>
                        <p class="text-[#64748B] mb-8">{{ __('Please login or create an account to continue checkout.') }}
                        </p>
                        <div class="flex gap-4">
                            <a href="{{ route('checkout.login', $intent) }}"
                                class="flex-1 bg-[#1E7CCF] text-white text-center py-4 rounded-xl font-bold">{{ __('Login to Continue') }}</a>
                            <a href="{{ route('checkout.register', $intent) }}"
                                class="flex-1 border-2 border-[#E2E8F0] text-center py-4 rounded-xl font-bold">{{ __('Create Account') }}</a>
                        </div>
                    @endguest

                    @auth
                        <h3 class="text-lg font-bold mb-6 flex items-center gap-2">
                            <span
                                class="w-8 h-8 bg-[#E9F2FB] text-[#1E7CCF] rounded-full flex items-center justify-center text-sm">2</span>
                            {{ __('Select Payment Method') }}
                        </h3>

                        @if($intent->type !== 'credit' && ($totals['available_credit'] ?? 0) > 0)
                            <div class="mb-6 p-4 rounded-2xl border border-green-200 bg-green-50">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" x-model="useCredit"
                                        class="w-5 h-5 text-green-600 rounded focus:ring-green-500 border-gray-300">
                                    <div class="flex-1">
                                        <p class="font-bold text-green-800">{{ __('Use Available Credit') }}</p>
                                        <p class="text-sm text-green-700">
                                            {{ __('Available Balance:') }}
                                            <span
                                                class="font-bold">{{ $currencyPrefix }}{{ number_format(($totals['available_credit'] ?? 0) / 100, 2) }}</span>
                                        </p>
                                    </div>
                                    <div class="font-bold text-green-800" x-show="useCredit">
                                        -{{ $currencyPrefix }}<span x-text="formatMoney(creditAppliedCents)">0.00</span>
                                    </div>
                                </label>
                            </div>
                        @endif

                        <div class="space-y-4" x-show="payNowCents > 0" x-transition>
                            <label
                                class="flex items-center justify-between p-4 border-2 border-[#1E7CCF] bg-[#E9F2FB] rounded-2xl cursor-pointer">
                                <div class="flex items-center gap-4">
                                    <input type="radio" name="payment_method" checked>
                                    <span class="font-bold">{{ __('Credit / Debit Card') }}</span>
                                </div>
                                <div class="flex gap-2">
                                    <div class="w-8 h-5 bg-gray-200 rounded"></div>
                                    <div class="w-8 h-5 bg-gray-300 rounded"></div>
                                </div>
                            </label>
                            {{-- PayPal option hidden for simplicity or kept if needed --}}
                        </div>

                        <div x-show="payNowCents === 0 && useCredit"
                            class="p-4 bg-[#F8FAFC] rounded-2xl border border-[#E2E8F0] text-center">
                            <p class="font-bold text-[#334155]">{{ __('Order fully covered by credit balance.') }}</p>
                        </div>

                        <form method="POST" action="{{ route('checkout.pay', $intent) }}">
                            @csrf
                            <input type="hidden" name="use_credit" :value="useCredit ? '1' : '0'">
                            <button type="submit"
                                class="w-full mt-10 bg-[#1E7CCF] text-white py-5 rounded-2xl font-bold text-xl shadow-xl shadow-blue-200">
                                <span x-show="payNowCents > 0">
                                    {{ __('Pay') }} {{ $currencyPrefix }}<span x-text="formatMoney(payNowCents)"></span>
                                </span>
                                <span x-show="payNowCents === 0">
                                    {{ __('Complete Order') }}
                                </span>
                            </button>
                        </form>

                        @if ($canFakePay)
                            <form method="POST" action="{{ route('checkout.fake-pay', $intent) }}" class="mt-4">
                                @csrf
                                <button type="submit"
                                    class="w-full border-2 border-[#E2E8F0] py-4 rounded-2xl font-bold text-[#334155] hover:bg-[#F8FAFC]">
                                    {{ __('Fake Pay (Dev)') }}
                                </button>
                            </form>
                            <div class="mt-4 flex justify-between items-center text-xs text-[#94A3B8]">
                                <p>{{ __('Available only in local/testing environments.') }}</p>
                                <form method="POST" action="{{ route('checkout.manual-payment', $intent) }}">
                                    @csrf
                                    <button type="submit" class="text-red-500 font-bold hover:underline">
                                        {{ __('Payment later / manual payment') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endauth
                </div>
            </div>

            <!-- Right: Order Summary Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-[#0F172A] text-white p-8 rounded-[2.5rem] sticky top-32">
                    <h3 class="text-xl font-bold mb-8">{{ __('Summary') }}</h3>
                    <div class="space-y-4 border-b border-slate-700 pb-8 mb-8">
                        <div class="flex justify-between">
                            <span class="text-slate-400">{{ __('Subtotal') }}</span>
                            <span>{{ $currencyPrefix }}{{ $formattedTotal }}</span>
                        </div>
                        <div class="flex justify-between text-green-400" x-show="useCredit && creditAppliedCents > 0"
                            x-transition>
                            <span class="text-green-400">{{ __('Credit Applied') }}</span>
                            <span>-{{ $currencyPrefix }}<span x-text="formatMoney(creditAppliedCents)"></span></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">{{ __('Processing Fee') }}</span>
                            <span>{{ $currencyPrefix }}0.00</span>
                        </div>
                    </div>
                    <div class="flex justify-between text-2xl font-black mb-10">
                        <span>{{ __('Total') }}</span>
                        <span>{{ $currencyPrefix }}<span
                                x-text="formatMoney(payNowCents)">{{ $formattedTotal }}</span></span>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        {{ __('By completing your purchase, you agree to the Terms of Service and Privacy Policy.') }}
                    </p>
                </div>
            </div>

        </div>
    </div>
</body>

</html>