<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Secure Checkout') }} | {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>

<body class="bg-[#F8FAFC]">
    @php
        $currencyPrefix = strtolower($intent->currency) === 'usd' ? '$' : strtoupper($intent->currency) . ' ';
    @endphp

    <div class="max-w-[1280px] mx-auto px-10 py-20">
        <div class="grid lg:grid-cols-3 gap-12">

            <!-- Left: Order Details (2 Columns) -->
            <div class="lg:col-span-2 space-y-8">
                <h1 class="text-3xl font-black text-[#0F172A]">{{ __('Complete Your Order') }}</h1>

                <!-- 1. Review Details -->
                <div class="bg-white p-8 rounded-3xl border border-[#E2E8F0] shadow-sm">
                    <h3 class="text-lg font-bold mb-6 flex items-center gap-2">
                        <span
                            class="w-8 h-8 bg-[#E9F2FB] text-[#1E7CCF] rounded-full flex items-center justify-center text-sm">1</span>
                        {{ __('Review Order Details') }}
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="p-4 bg-[#F8FAFC] rounded-2xl border border-[#E2E8F0]">
                            <p class="text-xs font-bold text-[#64748B] uppercase">{{ __('Emails to Verify') }}</p>
                            <p class="text-2xl font-black text-[#0F172A]">{{ number_format($intent->email_count) }}</p>
                            <p class="text-xs text-[#94A3B8] mt-2">{{ $intent->original_filename }}</p>
                        </div>
                        <div class="p-4 bg-[#E9F2FB] rounded-2xl border border-[#1E7CCF]/10">
                            <p class="text-xs font-bold text-[#1E7CCF] uppercase">{{ __('Total Price') }}</p>
                            <p class="text-2xl font-black text-[#1E7CCF]">{{ $currencyPrefix }}{{ $formattedTotal }}
                            </p>
                            <p class="text-xs text-[#1E7CCF] mt-2 font-semibold">
                                {{ $intent->pricingPlan?->name ?? __('Pay-as-you-go') }}
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
                        <div class="space-y-4">
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
                            <label
                                class="flex items-center justify-between p-4 border border-[#E2E8F0] rounded-2xl cursor-pointer hover:bg-[#F8FAFC]">
                                <div class="flex items-center gap-4">
                                    <input type="radio" name="payment_method">
                                    <span class="font-bold">{{ __('PayPal') }}</span>
                                </div>
                                <div class="w-16 h-5 bg-gray-200 rounded"></div>
                            </label>
                        </div>
                        <!-- MANUAL PAYMENT OPTION -->
                        <form method="POST" action="{{ route('checkout.pay', $intent) }}" id="payment-main-form">
                            @csrf
                            <button type="submit"
                                class="w-full mt-10 bg-[#1E7CCF] text-white py-5 rounded-2xl font-bold text-xl shadow-xl shadow-blue-100">
                                <span id="pay-button-text">{{ __('Pay Securely Now') }}</span>
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
                            <p class="mt-2 text-xs text-[#94A3B8]">
                                {{ __('Available only in local/testing environments.') }}</p>
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
                        <div class="flex justify-between">
                            <span class="text-slate-400">{{ __('Processing Fee') }}</span>
                            <span>{{ $currencyPrefix }}0.00</span>
                        </div>
                    </div>
                    <div class="flex justify-between text-2xl font-black mb-10">
                        <span>{{ __('Total') }}</span>
                        <span>{{ $currencyPrefix }}{{ $formattedTotal }}</span>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        {{ __('By completing your purchase, you agree to the Terms of Service and Privacy Policy.') }}
                    </p>
                </div>
            </div>

        </div>
    </div>
    <!-- Add Lucide Icons and Logic -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        // 1. Get the form and the radio buttons
        const mainForm = document.getElementById('payment-main-form');
        const payButton = document.getElementById('pay-button-text');

        // 2. Logic to switch routes based on selection
        mainForm.addEventListener('submit', function(e) {
            // Find which radio is checked
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;

            if (selectedMethod === 'manual') {
                // Change the form action to the Fake Pay route
                this.action = "{{ route('checkout.fake-pay', $intent) }}";
            } else {
                // Reset to standard pay route
                this.action = "{{ route('checkout.pay', $intent) }}";
            }
        });

        // 3. UI Polish: Change button text when radio changes
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'manual') {
                    payButton.innerText = "{{ __('Confirm Manual Payment') }}";
                } else {
                    payButton.innerText = "{{ __('Pay Securely Now') }}";
                }
            });
        });
    </script>
</body>

</html>
