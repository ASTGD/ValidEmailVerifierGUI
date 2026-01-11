<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | ValidEmail</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="bg-[#F8FAFC]">

    <div class="max-w-[1280px] mx-auto px-10 py-20">
        <div class="grid lg:grid-cols-3 gap-12">

            <!-- Left: Order Details (2 Columns) -->
            <div class="lg:col-span-2 space-y-8">
                <h1 class="text-3xl font-black text-[#0F172A]">Complete Your Order</h1>

                <!-- 1. Review Details -->
                <div class="bg-white p-8 rounded-3xl border border-[#E2E8F0] shadow-sm">
                    <h3 class="text-lg font-bold mb-6 flex items-center gap-2">
                        <span class="w-8 h-8 bg-[#E9F2FB] text-[#1E7CCF] rounded-full flex items-center justify-center text-sm">1</span>
                        Review Order Details
                    </h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div class="p-4 bg-[#F8FAFC] rounded-2xl border border-[#E2E8F0]">
                            <p class="text-xs font-bold text-[#64748B] uppercase">Emails to Verify</p>
                            <p class="text-2xl font-black text-[#0F172A]" id="display-count">0</p>
                        </div>
                        <div class="p-4 bg-[#E9F2FB] rounded-2xl border border-[#1E7CCF]/10">
                            <p class="text-xs font-bold text-[#1E7CCF] uppercase">Total Price</p>
                            <p class="text-2xl font-black text-[#1E7CCF]" id="display-price">$0.00</p>
                        </div>
                    </div>
                </div>

                <!-- 2. Auth/Payment Logic -->
                <div class="bg-white p-8 rounded-3xl border border-[#E2E8F0] shadow-sm">
                    @guest
                        <h3 class="text-lg font-bold mb-4">Account Required</h3>
                        <p class="text-[#64748B] mb-8">Please login or create an account to securely store your credits.</p>
                        <div class="flex gap-4">
                            <a href="http://localhost:8082/login" class="flex-1 bg-[#1E7CCF] text-white text-center py-4 rounded-xl font-bold">Login to Continue</a>
                            <a href="http://localhost:8082/register" class="flex-1 border-2 border-[#E2E8F0] text-center py-4 rounded-xl font-bold">Create Account</a>
                        </div>
                    @endguest

                    @auth
                        <h3 class="text-lg font-bold mb-6 flex items-center gap-2">
                            <span class="w-8 h-8 bg-[#E9F2FB] text-[#1E7CCF] rounded-full flex items-center justify-center text-sm">2</span>
                            Select Payment Method
                        </h3>
                        <div class="space-y-4">
                            <label class="flex items-center justify-between p-4 border-2 border-[#1E7CCF] bg-[#E9F2FB] rounded-2xl cursor-pointer">
                                <div class="flex items-center gap-4">
                                    <input type="radio" name="payment" checked>
                                    <span class="font-bold">Credit / Debit Card</span>
                                </div>
                                <div class="flex gap-2">
                                    <div class="w-8 h-5 bg-gray-200 rounded"></div>
                                    <div class="w-8 h-5 bg-gray-300 rounded"></div>
                                </div>
                            </label>
                            <label class="flex items-center justify-between p-4 border border-[#E2E8F0] rounded-2xl cursor-pointer hover:bg-[#F8FAFC]">
                                <div class="flex items-center gap-4">
                                    <input type="radio" name="payment">
                                    <span class="font-bold">PayPal</span>
                                </div>
                                <div class="w-16 h-5 bg-gray-200 rounded"></div>
                            </label>
                        </div>

                        <button onclick="alert('Redirecting to secure gateway...')" class="w-full mt-10 bg-[#1E7CCF] text-white py-5 rounded-2xl font-bold text-xl shadow-xl shadow-blue-200">
                            Pay Securely Now
                        </button>
                    @endauth
                </div>
            </div>

            <!-- Right: Order Summary Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-[#0F172A] text-white p-8 rounded-[2.5rem] sticky top-32">
                    <h3 class="text-xl font-bold mb-8">Summary</h3>
                    <div class="space-y-4 border-b border-slate-700 pb-8 mb-8">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Subtotal</span>
                            <span id="summary-subtotal">$0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Processing Fee</span>
                            <span>$0.00</span>
                        </div>
                    </div>
                    <div class="flex justify-between text-2xl font-black mb-10">
                        <span>Total</span>
                        <span id="summary-total">$0.00</span>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        By completing your purchase, you agree to ValidEmail's Terms of Service and Privacy Policy.
                    </p>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Get URL Params
        const urlParams = new URLSearchParams(window.location.search);
        const count = urlParams.get('count') || '0';
        const price = urlParams.get('price') || '0.00';

        // Update Page
        document.getElementById('display-count').innerText = parseInt(count).toLocaleString();
        document.getElementById('display-price').innerText = '$' + price;
        document.getElementById('summary-subtotal').innerText = '$' + price;
        document.getElementById('summary-total').innerText = '$' + price;
    </script>
</body>
</html>
