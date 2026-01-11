<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ValidEmail | Premium Email Verification</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>

<body class="bg-[#F8FAFC]">

    <!-- 1. NAVIGATION (Solid & Balanced) -->
    <nav class="nav-sticky">
        <div class="max-w-[1280px] mx-auto px-10 h-20 flex items-center justify-between">

            <!-- Logo (Left) -->
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <div
                    class="w-10 h-10 bg-[#1E7CCF] rounded-xl flex items-center justify-center shadow-lg shadow-blue-200">
                    <i data-lucide="shield-check" class="text-white w-6 h-6"></i>
                </div>
                <span class="text-xl font-extrabold text-[#0F172A] tracking-tight">ValidEmail</span>
            </a>

            <!-- Grouped Actions (Right) -->
            <div class="flex items-center gap-10">
                <!-- Links -->
                <div class="hidden md:flex items-center gap-8 font-semibold text-[#334155] text-[15px]">
                    <a href="#features" class="hover:text-[#1E7CCF] transition-all">Features</a>
                    <a href="#how-it-works" class="hover:text-[#1E7CCF] transition-all">How it works</a>
                    <a href="#pricing" class="hover:text-[#1E7CCF] transition-all">Pricing</a>
                </div>

                <!-- Divider -->
                <div class="hidden md:block w-px h-6 bg-[#E2E8F0]"></div>

                <!-- Auth Buttons -->
                <div class="flex items-center gap-4">
                    @guest
                        <a href="{{ route('login') }}"
                            class="text-[#334155] font-bold hover:text-[#1E7CCF] transition-all">Login</a>
                        <a href="{{ route('register') }}"
                            class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-6 py-2.5 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all">
                            Sign Up
                        </a>
                    @endguest

                    @auth
                        <a href="{{ route('portal.dashboard') }}"
                            class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-6 py-2.5 rounded-xl font-bold transition-all flex items-center gap-2">
                            Dashboard <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    @endauth
                </div>
            </div>

        </div>
    </nav>

    <!-- 2. HERO SECTION (Maintained Industry Spacing) -->
    <header class="w-full hero-bg pt-24 pb-32">
        <div class="max-w-[1280px] mx-auto px-10 text-center">
            <div
                class="inline-flex items-center gap-2 bg-[#E9F2FB] text-[#1E7CCF] px-4 py-2 rounded-full text-sm font-bold mb-8 border border-blue-100">
                <span class="flex h-2 w-2 rounded-full bg-[#1E7CCF]"></span>
                99.9% Accuracy Guaranteed
            </div>

            <h1 class="text-5xl md:text-7xl font-[800] text-[#0F172A] mb-8 leading-[1.1] tracking-tight">
                Verify Your Email Lists <br>
                <span class="text-[#1E7CCF]">In Real-Time.</span>
            </h1>

            <p class="text-lg md:text-xl text-[#64748B] max-w-2xl mx-auto mb-12 leading-relaxed">
                Clean your mailing list instantly. No subscriptions. No complex setups. Just high-accuracy verification
                that ensures your emails reach the inbox.
            </p>

            <div class="flex flex-col md:flex-row items-center justify-center gap-4">
                <a href="#pricing"
                    class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-10 py-4 rounded-xl text-lg font-bold shadow-xl shadow-blue-200 transition-all">
                    Get Started Now
                </a>
                <a href="#how-it-works"
                    class="px-10 py-4 rounded-xl text-lg font-bold text-[#334155] border-2 border-[#E2E8F0] hover:bg-[#F8FAFC] transition-all">
                    Learn More
                </a>
            </div>
        </div>
    </header>

    <!-- SECTION 3: STATISTICS -->
    <section class="w-full bg-[#0F172A] text-white py-20 border-y border-[#E2E8F0]">
        <div class="max-w-[1280px] mx-auto px-10">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-12 text-center">
                <div>
                    <h3 class="text-4xl font-extrabold text-white mb-2">10M+</h3>
                    <p class="text-[#64748B] font-medium uppercase text-xs tracking-widest">Emails Verified</p>
                </div>
                <div>
                    <h3 class="text-4xl font-extrabold text-white mb-2">99.9%</h3>
                    <p class="text-[#64748B] font-medium uppercase text-xs tracking-widest">Accuracy Rate</p>
                </div>
                <div>
                    <h3 class="text-4xl font-extrabold text-white mb-2">5,000+</h3>
                    <p class="text-[#64748B] font-medium uppercase text-xs tracking-widest">Happy Clients</p>
                </div>
                <div>
                    <h3 class="text-4xl font-extrabold text-white mb-2">24/7</h3>
                    <p class="text-[#64748B] font-medium uppercase text-xs tracking-widest">System Uptime</p>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 4: SPLIT PRICING & TIERED CALCULATOR (UNIFORM HEIGHT) -->
    <section id="pricing" class="w-full py-32 bg-[#F1F5F9]">
        <div class="max-w-[1280px] mx-auto px-10">
            <!-- added items-stretch to ensure children have same height -->
            <div class="grid lg:grid-cols-2 gap-16 items-stretch">

                <!-- Left Side: Tutorial & Tiers -->
                <div class="flex flex-col">
                    <div
                        class="inline-flex items-center gap-2 bg-white text-[#1E7CCF] px-4 py-2 rounded-full text-sm font-bold mb-6 shadow-sm w-fit">
                        <i data-lucide="tag" class="w-4 h-4"></i> Pay-As-You-Go
                    </div>
                    <h2 class="text-4xl md:text-5xl font-black text-[#0F172A] mb-8 tracking-tight leading-tight">
                        Smart Pricing for <br><span class="text-[#1E7CCF]">Smart Marketers.</span>
                    </h2>

                    <div class="space-y-8 mb-12">
                        <div class="flex gap-5">
                            <div
                                class="w-10 h-10 shrink-0 bg-[#1E7CCF] text-white rounded-lg flex items-center justify-center font-bold">
                                1</div>
                            <div>
                                <h4 class="text-lg font-bold text-[#0F172A]">Upload Your List</h4>
                                <p class="text-[#64748B]">Drag and drop your XLSX, XLS, CSV, or TXT file into the calculator.</p>
                            </div>
                        </div>
                        <div class="flex gap-5">
                            <div
                                class="w-10 h-10 shrink-0 bg-[#1E7CCF] text-white rounded-lg flex items-center justify-center font-bold">
                                2</div>
                            <div>
                                <h4 class="text-lg font-bold text-[#0F172A]">Instant Analysis</h4>
                                <p class="text-[#64748B]">Our system counts valid emails and applies the best tier rate.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-8 shadow-xl border border-white mt-auto">
                        <h4 class="font-bold text-[#0F172A] mb-6 flex items-center gap-2">
                            <i data-lucide="bar-chart-3" class="text-[#1E7CCF]"></i> Volume Discount Tiers
                        </h4>
                        <div class="space-y-4">
                            <div
                                class="flex justify-between items-center p-3 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0]">
                                <span class="font-semibold text-[#334155]">1 - 5,000 Emails</span>
                                <span class="text-[#1E7CCF] font-bold">$0.03 / ea</span>
                            </div>
                            <div
                                class="flex justify-between items-center p-3 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0]">
                                <span class="font-semibold text-[#334155]">5,001 - 15,000 Emails</span>
                                <span class="text-[#1E7CCF] font-bold">$0.02 / ea</span>
                            </div>
                            <div
                                class="flex justify-between items-center p-3 rounded-xl bg-[#E9F2FB] border border-[#1E7CCF]/20">
                                <span class="font-bold text-[#1E7CCF]">15,001 - 50,000 Emails</span>
                                <span class="text-[#1E7CCF] font-black">$0.01 / ea</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: The Interactive Tool (Now Uniform Height) -->
                <div class="flex flex-col h-full">
                    <div
                        class="bg-white p-6 rounded-[3rem] shadow-2xl border border-white flex flex-col h-full relative overflow-hidden">
                        <!-- Drop zone now uses flex-grow to fill all available space -->
                        <div id="drop-zone"
                            class="border-2 border-dashed border-[#CBD5E1] rounded-[2.5rem] p-12 text-center transition-all hover:border-[#1E7CCF] bg-[#F8FAFC] cursor-pointer group flex flex-col items-center justify-center flex-grow">
                            <input type="file" id="file-input" hidden accept=".xls,.xlsx,.csv,.txt">

                            <!-- Initial State -->
                            <div id="calc-initial" class="flex flex-col items-center justify-center">
                                <div
                                    class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center mb-6 shadow-sm group-hover:scale-110 transition-transform">
                                    <i data-lucide="upload-cloud" class="text-[#1E7CCF] w-10 h-10"></i>
                                </div>
                                <h3 class="text-2xl font-bold text-[#0F172A] mb-2">Upload Your List</h3>
                                <p class="text-[#64748B] mb-8 font-medium px-4">Click to browse or drag & drop</p>
                                <button
                                    class="bg-[#1E7CCF] text-white px-10 py-4 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all">
                                    Select File
                                </button>
                            </div>

                            <!-- Processing State -->
                            <div id="calc-processing" class="hidden flex flex-col items-center justify-center py-10">
                                <div
                                    class="animate-spin rounded-full h-16 w-16 border-4 border-[#E9F2FB] border-t-[#1E7CCF] mb-6">
                                </div>
                                <h3 class="text-xl font-bold text-[#0F172A]">Analyzing Tiers...</h3>
                            </div>

                            <!-- Result State -->
                            <div id="calc-result" class="hidden w-full text-left">
                                <div class="space-y-4 mb-8">
                                    <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0]">
                                        <p class="text-[#64748B] text-xs font-bold uppercase tracking-widest mb-1">
                                            Email Count</p>
                                        <h4 id="calc-email-count" class="text-4xl font-black text-[#0F172A]">0</h4>
                                    </div>
                                    <div id="price-card"
                                        class="bg-[#E9F2FB] p-6 rounded-2xl border border-[#1E7CCF]/20">
                                        <p class="text-[#1E7CCF] text-xs font-bold uppercase tracking-widest mb-1">
                                            Total Quote</p>
                                        <h4 id="calc-total-price" class="text-4xl font-black text-[#1E7CCF]">$0.00
                                        </h4>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-3">
                                    <button id="payout-redirect"
                                        class="w-full bg-[#1E7CCF] hover:bg-[#1866AD] text-white py-5 rounded-2xl font-bold text-lg shadow-xl shadow-blue-100 transition-all">Proceed
                                        to Checkout</button>
                                    <button onclick="location.reload()"
                                        class="w-full py-4 text-[#64748B] font-bold hover:text-[#0F172A]">Clear and
                                        Restart</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- SECTION 5: WHY CHOOSE US -->
    <section id="features" class="w-full py-32 bg-white">
        <div class="max-w-[1280px] mx-auto px-10">
            <div class="flex flex-col md:flex-row justify-between items-end mb-20 gap-8">
                <div class="max-w-2xl">
                    <h2 class="text-4xl md:text-5xl font-black text-[#0F172A] mb-6 tracking-tight">Built for Enterprise
                        Accuracy.</h2>
                    <p class="text-xl text-[#64748B] font-medium leading-relaxed">
                        We use a multi-layer verification process to ensure your emails reach real humans, not spam
                        folders.
                    </p>
                </div>
                <a href="{{ route('register') }}"
                    class="text-[#1E7CCF] font-bold text-lg flex items-center gap-2 hover:gap-4 transition-all">
                    See all features <i data-lucide="arrow-right"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <!-- Feature 1 -->
                <div
                    class="group p-10 rounded-[2rem] bg-[#F8FAFC] border border-[#E2E8F0] hover:bg-white hover:shadow-2xl hover:shadow-blue-900/5 transition-all">
                    <div
                        class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mb-8 shadow-sm group-hover:bg-[#E9F2FB] transition-colors">
                        <i data-lucide="zap" class="text-[#1E7CCF] w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-[#0F172A] mb-4">Real-time Speed</h3>
                    <p class="text-[#64748B] leading-relaxed font-medium">
                        Verify up to 10,000 emails per minute with our distributed cloud infrastructure. No waiting in
                        line.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div
                    class="group p-10 rounded-[2rem] bg-[#F8FAFC] border border-[#E2E8F0] hover:bg-white hover:shadow-2xl hover:shadow-blue-900/5 transition-all">
                    <div
                        class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mb-8 shadow-sm group-hover:bg-[#DCFCE7] transition-colors">
                        <i data-lucide="shield-check" class="text-[#16A34A] w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-[#0F172A] mb-4">99.9% Accuracy</h3>
                    <p class="text-[#64748B] leading-relaxed font-medium">
                        Our proprietary algorithm checks SMTP, DNS, and MX records to eliminate hard bounces completely.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div
                    class="group p-10 rounded-[2rem] bg-[#F8FAFC] border border-[#E2E8F0] hover:bg-white hover:shadow-2xl hover:shadow-blue-900/5 transition-all">
                    <div
                        class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mb-8 shadow-sm group-hover:bg-[#E0F2FE] transition-colors">
                        <i data-lucide="lock" class="text-[#0EA5E9] w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-[#0F172A] mb-4">GDPR Compliant</h3>
                    <p class="text-[#64748B] leading-relaxed font-medium">
                        Your data is encrypted and automatically deleted after processing. We never store or sell your
                        lists.
                    </p>
                </div>
                <!-- Feature 4 -->
                <div
                    class="group p-10 rounded-[2rem] bg-[#F8FAFC] border border-[#E2E8F0] hover:bg-white hover:shadow-2xl hover:shadow-blue-900/5 transition-all">
                    <div
                        class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mb-8 shadow-sm group-hover:bg-[#E9F2FB] transition-colors">
                        <i data-lucide="zap" class="text-[#1E7CCF] w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-[#0F172A] mb-4">Real-time Speed</h3>
                    <p class="text-[#64748B] leading-relaxed font-medium">
                        Verify up to 10,000 emails per minute with our distributed cloud infrastructure. No waiting in
                        line.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div
                    class="group p-10 rounded-[2rem] bg-[#F8FAFC] border border-[#E2E8F0] hover:bg-white hover:shadow-2xl hover:shadow-blue-900/5 transition-all">
                    <div
                        class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mb-8 shadow-sm group-hover:bg-[#DCFCE7] transition-colors">
                        <i data-lucide="shield-check" class="text-[#16A34A] w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-[#0F172A] mb-4">99.9% Accuracy</h3>
                    <p class="text-[#64748B] leading-relaxed font-medium">
                        Our proprietary algorithm checks SMTP, DNS, and MX records to eliminate hard bounces completely.
                    </p>
                </div>

                <!-- Feature 6 -->
                <div
                    class="group p-10 rounded-[2rem] bg-[#F8FAFC] border border-[#E2E8F0] hover:bg-white hover:shadow-2xl hover:shadow-blue-900/5 transition-all">
                    <div
                        class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mb-8 shadow-sm group-hover:bg-[#E0F2FE] transition-colors">
                        <i data-lucide="lock" class="text-[#0EA5E9] w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-[#0F172A] mb-4">GDPR Compliant</h3>
                    <p class="text-[#64748B] leading-relaxed font-medium">
                        Your data is encrypted and automatically deleted after processing. We never store or sell your
                        lists.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 6: HOW IT WORKS (RE-DESIGNED) -->
    <section id="how-it-works" class="w-full py-32 bg-white relative overflow-hidden">
        <!-- Background Decor -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full opacity-[0.03] pointer-events-none">
            <svg width="100%" height="100%" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 100h1280M0 200h1280M0 300h1280" stroke="#1E7CCF" stroke-width="1" />
            </svg>
        </div>

        <div class="max-w-[1280px] mx-auto px-10 relative z-10">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-20">
                <span class="text-[#1E7CCF] font-bold uppercase tracking-widest text-sm">The Process</span>
                <h2 class="text-4xl md:text-5xl font-black text-[#0F172A] mt-4 mb-6 tracking-tight">
                    Enterprise-Grade Verification <br> in <span class="text-[#1E7CCF]">Three Simple Steps</span>
                </h2>
                <p class="text-lg text-[#64748B] font-medium leading-relaxed">
                    Our proprietary 12-point verification engine ensures your emails reach the primary inbox, protecting
                    your sender reputation automatically.
                </p>
            </div>

            <!-- Steps Grid -->
            <div class="grid lg:grid-cols-3 gap-8 relative">

                <!-- Connecting Line (Desktop Only) -->
                <div
                    class="hidden lg:block absolute top-1/3 left-[15%] right-[15%] h-px border-t-2 border-dashed border-[#E2E8F0] -z-0">
                </div>

                <!-- Step 1 -->
                <div
                    class="group relative bg-[#F8FAFC] p-10 rounded-[2.5rem] border border-[#E2E8F0] hover:bg-white hover:shadow-2xl hover:shadow-blue-900/5 transition-all duration-500 z-10">
                    <div
                        class="w-16 h-16 bg-[#1E7CCF] text-white rounded-2xl flex items-center justify-center mb-8 shadow-lg shadow-blue-200 group-hover:scale-110 transition-transform">
                        <i data-lucide="upload-cloud" class="w-8 h-8"></i>
                    </div>
                    <div
                        class="absolute top-8 right-8 text-6xl font-black text-[#0F172A]/[0.03] group-hover:text-[#1E7CCF]/[0.05] transition-colors">
                        01</div>
                    <h3 class="text-2xl font-bold text-[#0F172A] mb-4">Secure Data Upload</h3>
                    <p class="text-[#64748B] leading-relaxed font-medium mb-6">
                        Upload your CSV, TXT, or Excel list. We immediately apply **AES-256 encryption** to your data.
                        Your lists are never stored long-term and are automatically purged after processing.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center gap-2 text-sm font-bold text-[#334155]">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-[#16A34A]"></i> Bulk Import Support
                        </li>
                        <li class="flex items-center gap-2 text-sm font-bold text-[#334155]">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-[#16A34A]"></i> Auto-formatting
                        </li>
                    </ul>
                </div>

                <!-- Step 2 -->
                <div
                    class="group relative bg-[#F8FAFC] p-10 rounded-[2.5rem] border border-[#E2E8F0] hover:bg-white hover:shadow-2xl hover:shadow-blue-900/5 transition-all duration-500 z-10">
                    <div
                        class="w-16 h-16 bg-[#1E7CCF] text-white rounded-2xl flex items-center justify-center mb-8 shadow-lg shadow-blue-200 group-hover:scale-110 transition-transform">
                        <i data-lucide="cpu" class="w-8 h-8"></i>
                    </div>
                    <div
                        class="absolute top-8 right-8 text-6xl font-black text-[#0F172A]/[0.03] group-hover:text-[#1E7CCF]/[0.05] transition-colors">
                        02</div>
                    <h3 class="text-2xl font-bold text-[#0F172A] mb-4">AI-Powered Validation</h3>
                    <p class="text-[#64748B] leading-relaxed font-medium mb-6">
                        Our engine performs a **real-time SMTP handshake**, checks MX records, and filters out
                        disposable providers. We detect "catch-all" servers and syntax errors without ever sending a
                        single email.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center gap-2 text-sm font-bold text-[#334155]">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-[#16A34A]"></i> Real-time SMTP Ping
                        </li>
                        <li class="flex items-center gap-2 text-sm font-bold text-[#334155]">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-[#16A34A]"></i> Domain & MX Check
                        </li>
                    </ul>
                </div>

                <!-- Step 3 -->
                <div
                    class="group relative bg-[#F8FAFC] p-10 rounded-[2.5rem] border border-[#E2E8F0] hover:bg-white hover:shadow-2xl hover:shadow-blue-900/5 transition-all duration-500 z-10">
                    <div
                        class="w-16 h-16 bg-[#1E7CCF] text-white rounded-2xl flex items-center justify-center mb-8 shadow-lg shadow-blue-200 group-hover:scale-110 transition-transform">
                        <i data-lucide="download" class="w-8 h-8"></i>
                    </div>
                    <div
                        class="absolute top-8 right-8 text-6xl font-black text-[#0F172A]/[0.03] group-hover:text-[#1E7CCF]/[0.05] transition-colors">
                        03</div>
                    <h3 class="text-2xl font-bold text-[#0F172A] mb-4">Export Clean Data</h3>
                    <p class="text-[#64748B] leading-relaxed font-medium mb-6">
                        Receive a segmented report of **Deliverable**, **Risky**, and **Undeliverable** emails. Download
                        the clean list and integrate it directly with your favorite ESP like Mailchimp or HubSpot.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center gap-2 text-sm font-bold text-[#334155]">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-[#16A34A]"></i> Segmented Reporting
                        </li>
                        <li class="flex items-center gap-2 text-sm font-bold text-[#334155]">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-[#16A34A]"></i> Ready for Inbox
                        </li>
                    </ul>
                </div>

            </div>

            <!-- Summary Bottom Bar -->
            <div
                class="mt-20 p-8 rounded-[2rem] bg-[#E9F2FB] border border-[#1E7CCF]/10 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div
                        class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-[#1E7CCF] shadow-sm">
                        <i data-lucide="info" class="w-6 h-6"></i>
                    </div>
                    <p class="text-[#1E7CCF] font-bold text-lg">Did you know? Verifying your list can increase ROI by
                        up to 25%.</p>
                </div>
                <a href="{{ route('register') }}"
                    class="bg-[#1E7CCF] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#1866AD] transition-all whitespace-nowrap">
                    Verify My List Now
                </a>
            </div>
        </div>
    </section>

    <!-- SECTION 7: TESTIMONIALS -->
    <section class="py-32 bg-[#F8FAFC]">
        <div class="max-w-[1280px] mx-auto px-10 grid md:grid-cols-2 gap-10">
            <div class="bg-white p-10 rounded-3xl shadow-sm border border-[#E2E8F0]">
                <p class="text-lg italic mb-6">"The best verification tool we've used. Our bounce rates dropped to
                    almost zero."</p>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-slate-200 rounded-full"></div>
                    <div>
                        <h4 class="font-bold">Sarah Jenkins</h4>
                        <p class="text-sm text-[#64748B]">Email Marketer at TechFlow</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-10 rounded-3xl shadow-sm border border-[#E2E8F0]">
                <p class="text-lg italic mb-6">"The best verification tool we've used. Our bounce rates dropped to
                    almost zero."</p>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-slate-200 rounded-full"></div>
                    <div>
                        <h4 class="font-bold">Sarah Jenkins</h4>
                        <p class="text-sm text-[#64748B]">Email Marketer at TechFlow</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-10 rounded-3xl shadow-sm border border-[#E2E8F0]">
                <p class="text-lg italic mb-6">"The best verification tool we've used. Our bounce rates dropped to
                    almost zero."</p>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-slate-200 rounded-full"></div>
                    <div>
                        <h4 class="font-bold">Sarah Jenkins</h4>
                        <p class="text-sm text-[#64748B]">Email Marketer at TechFlow</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-10 rounded-3xl shadow-sm border border-[#E2E8F0]">
                <p class="text-lg italic mb-6">"Pay-as-you-go is perfect for our agency. We only pay when we have new
                    clients."</p>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-slate-200 rounded-full"></div>
                    <div>
                        <h4 class="font-bold">Mark Robison</h4>
                        <p class="text-sm text-[#64748B]">CEO, GrowthLabs</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 8: FAQ -->
    <section id="faq" class="py-32 bg-white">
        <div class="max-w-[800px] mx-auto px-10">
            <h2 class="text-center text-4xl font-black mb-16">Common Questions</h2>
            <div class="space-y-4">
                <details class="group border border-[#E2E8F0] rounded-2xl p-6">
                    <summary class="list-none font-bold text-lg cursor-pointer flex justify-between">
                        How accurate is the verification?
                        <span class="text-[#1E7CCF]">+</span>
                    </summary>
                    <p class="mt-4 text-[#64748B]">We guarantee 99.9% accuracy through our multi-step SMTP and DNS
                        checking process.</p>
                </details>
                <details class="group border border-[#E2E8F0] rounded-2xl p-6">
                    <summary class="list-none font-bold text-lg cursor-pointer flex justify-between">
                        How accurate is the verification?
                        <span class="text-[#1E7CCF]">+</span>
                    </summary>
                    <p class="mt-4 text-[#64748B]">We guarantee 99.9% accuracy through our multi-step SMTP and DNS
                        checking process.</p>
                </details>
                <details class="group border border-[#E2E8F0] rounded-2xl p-6">
                    <summary class="list-none font-bold text-lg cursor-pointer flex justify-between">
                        How accurate is the verification?
                        <span class="text-[#1E7CCF]">+</span>
                    </summary>
                    <p class="mt-4 text-[#64748B]">We guarantee 99.9% accuracy through our multi-step SMTP and DNS
                        checking process.</p>
                </details>
                <details class="group border border-[#E2E8F0] rounded-2xl p-6">
                    <summary class="list-none font-bold text-lg cursor-pointer flex justify-between">
                        How accurate is the verification?
                        <span class="text-[#1E7CCF]">+</span>
                    </summary>
                    <p class="mt-4 text-[#64748B]">We guarantee 99.9% accuracy through our multi-step SMTP and DNS
                        checking process.</p>
                </details>
                <details class="group border border-[#E2E8F0] rounded-2xl p-6">
                    <summary class="list-none font-bold text-lg cursor-pointer flex justify-between">
                        How accurate is the verification?
                        <span class="text-[#1E7CCF]">+</span>
                    </summary>
                    <p class="mt-4 text-[#64748B]">We guarantee 99.9% accuracy through our multi-step SMTP and DNS
                        checking process.</p>
                </details>
                <details class="group border border-[#E2E8F0] rounded-2xl p-6">
                    <summary class="list-none font-bold text-lg cursor-pointer flex justify-between">
                        How accurate is the verification?
                        <span class="text-[#1E7CCF]">+</span>
                    </summary>
                    <p class="mt-4 text-[#64748B]">We guarantee 99.9% accuracy through our multi-step SMTP and DNS
                        checking process.</p>
                </details>
                <details class="group border border-[#E2E8F0] rounded-2xl p-6">
                    <summary class="list-none font-bold text-lg cursor-pointer flex justify-between">
                        How accurate is the verification?
                        <span class="text-[#1E7CCF]">+</span>
                    </summary>
                    <p class="mt-4 text-[#64748B]">We guarantee 99.9% accuracy through our multi-step SMTP and DNS
                        checking process.</p>
                </details>
                <details class="group border border-[#E2E8F0] rounded-2xl p-6">
                    <summary class="list-none font-bold text-lg cursor-pointer flex justify-between">
                        Do you store my data?
                        <span class="text-[#1E7CCF]">+</span>
                    </summary>
                    <p class="mt-4 text-[#64748B]">No. Your lists are encrypted during processing and deleted
                        immediately after you download them.</p>
                </details>
            </div>
        </div>
    </section>

    <!-- SECTION 10: FOOTER -->
    <footer class="bg-[#0F172A] text-white py-20">
        <div class="max-w-[1280px] mx-auto px-10 grid md:grid-cols-4 gap-12">
            <div class="col-span-2">
                <h3 class="text-2xl font-bold mb-6">ValidEmail</h3>
                <p class="text-[#94A3B8] max-w-xs">Helping businesses reach the inbox with advanced email intelligence.
                </p>
            </div>
            <div>
                <h4 class="font-bold mb-6">Product</h4>
                <ul class="space-y-4 text-[#94A3B8]">
                    <li><a href="#pricing">Pricing</a></li>
                    <li><a href="#how-it-works">Features</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-6">Company</h4>
                <ul class="space-y-4 text-[#94A3B8]">
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                </ul>
            </div>
        </div>
        <div class="max-w-[1280px] mx-auto px-10 mt-20 pt-10 border-t border-slate-800 text-[#64748B] text-center">
            © {{ date('Y') }} ValidEmail. All rights reserved.
        </div>
    </footer>

    <script>
        lucide.createIcons();

        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const initialUI = document.getElementById('calc-initial');
        const processingUI = document.getElementById('calc-processing');
        const resultUI = document.getElementById('calc-result');
        const checkoutUrl = @json(route('checkout'));

        dropZone.onclick = () => {
            if (resultUI.classList.contains('hidden')) fileInput.click();
        };

        fileInput.onchange = (e) => {
            const file = e.target.files[0];
            if (!file) return;

            initialUI.classList.add('hidden');
            processingUI.classList.remove('hidden');

            const fileName = file.name.toLowerCase();
            const reader = new FileReader();

            if (fileName.endsWith('.xlsx') || fileName.endsWith('.xls')) {
                reader.onload = (event) => {
                    const data = new Uint8Array(event.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });

                    let allText = '';
                    workbook.SheetNames.forEach((sheetName) => {
                        const worksheet = workbook.Sheets[sheetName];
                        allText += JSON.stringify(XLSX.utils.sheet_to_json(worksheet));
                    });

                    processEmailMatches(allText);
                };
                reader.readAsArrayBuffer(file);
            } else {
                reader.onload = (event) => {
                    processEmailMatches(event.target.result);
                };
                reader.readAsText(file);
            }
        };

        function processEmailMatches(text) {
            const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
            const emails = text.match(emailRegex) || [];
            const count = emails.length;

            let rate = 0.03;
            if (count > 5000) rate = 0.02;
            if (count > 15000) rate = 0.01;
            const total = (count * rate).toFixed(2);

            setTimeout(() => {
                document.getElementById('calc-email-count').innerText = count.toLocaleString();
                document.getElementById('calc-total-price').innerText = '$' + total;

                processingUI.classList.add('hidden');
                resultUI.classList.remove('hidden');

                document.getElementById('payout-redirect').onclick = () => {
                    window.location.href = `${checkoutUrl}?count=${count}&price=${total}`;
                };
            }, 800);
        }
    </script>
</body>

</html>
