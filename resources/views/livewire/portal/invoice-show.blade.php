<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('portal.invoices.index') }}"
                    class="text-sm font-bold text-[#1E7CCF] hover:underline flex items-center gap-1">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    {{ __('Back to Invoices') }}
                </a>
            </div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">
                {{ __('Invoice') }} #{{ $invoice->invoice_number }}
            </h1>

            @if(session('status'))
                <div class="mt-4 p-4 rounded-lg bg-green-50 border border-green-100 text-green-800 font-bold">
                    {{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="mt-4 p-4 rounded-lg bg-red-50 border border-red-100 text-red-800 font-bold">
                    {{ session('error') }}</div>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="download"
                class="flex items-center gap-2 px-6 py-3 bg-[#1E7CCF] hover:bg-[#1669B2] text-white rounded-xl shadow-lg transition-all font-bold">
                <i data-lucide="download" class="w-4 h-4"></i>
                {{ __('Download PDF') }}
            </button>
        </div>
    </div>

    {{-- Premium Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Card 1: Overview --}}
        <div class="flex flex-col min-h-[180px] bg-white border border-[#e2e8f0] rounded-2xl p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-4 border-b border-[#f1f5f9] pb-3">
                <i data-lucide="info" class="w-4 h-4 text-[#64748b]"></i>
                <h2 class="text-[11px] font-bold text-[#64748b] uppercase tracking-widest">{{ __('Invoice Overview') }}
                </h2>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span
                        class="text-[11px] text-[#64748b] font-semibold uppercase tracking-tight">{{ __('Status') }}</span>
                     <span class="px-3 py-1 rounded-md text-[10px] font-extrabold uppercase
                        @if($invoice->status === 'Paid') bg-green-100 text-green-700
                        @elseif($invoice->status === 'Partially Paid') bg-orange-100 text-orange-700
                        @elseif($invoice->status === 'Unpaid') bg-red-100 text-red-700
                        @elseif($invoice->status === 'Cancelled') bg-slate-100 text-slate-700
                        @else bg-blue-100 text-blue-700 @endif">
                        {{ $invoice->status }}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span
                        class="text-[11px] text-[#64748b] font-semibold uppercase tracking-tight">{{ __('Issued') }}</span>
                    <span class="text-xs text-[#334155] font-medium">{{ $invoice->date?->format('F d, Y') }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span
                        class="text-[11px] text-[#64748b] font-semibold uppercase tracking-tight">{{ __('Due Date') }}</span>
                    <span class="text-xs text-[#334155] font-medium">{{ $invoice->due_date?->format('F d, Y') }}</span>
                </div>
            </div>
        </div>

        {{-- Card 2: Payment History --}}
        <div class="flex flex-col min-h-[180px] bg-white border border-[#e2e8f0] rounded-2xl p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-4 border-b border-[#f1f5f9] pb-3">
                <i data-lucide="credit-card" class="w-4 h-4 text-[#64748b]"></i>
                <h2 class="text-[11px] font-bold text-[#64748b] uppercase tracking-widest">{{ __('Last Transaction') }}
                </h2>
            </div>
            @if($invoice->transactions->isEmpty())
                <div class="flex-1 flex flex-col items-center justify-center opacity-40">
                    <i data-lucide="clock" class="w-8 h-8 text-[#94a3b8]"></i>
                    <span
                        class="text-[10px] uppercase font-bold text-[#94a3b8] mt-2 tracking-widest">{{ __('No records') }}</span>
                </div>
            @else
                @php $latestTx = $invoice->transactions->sortByDesc('date')->first(); @endphp
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span
                            class="text-[11px] text-[#64748b] font-semibold uppercase tracking-tight">{{ __('Date') }}</span>
                        <span class="text-xs text-[#334155] font-medium">{{ $latestTx->date?->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span
                            class="text-[11px] text-[#64748b] font-semibold uppercase tracking-tight">{{ __('Method') }}</span>
                        <span
                            class="px-2 py-0.5 bg-[#f8fafc] border border-[#f1f5f9] rounded text-[10px] font-bold text-[#475569] uppercase">{{ $latestTx->payment_method }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span
                            class="text-[11px] text-[#64748b] font-semibold uppercase tracking-tight">{{ __('Amount') }}</span>
                        <span class="text-sm text-[#059669] font-bold">{{ number_format($latestTx->amount / 100, 2) }}
                            {{ strtoupper($invoice->currency) }}</span>
                    </div>
                </div>
            @endif
        </div>

        {{-- Card 3: Financials --}}
        @php
            // Calculate dynamically from items and transactions to ensure perfect accuracy
            $subtotalInCents = $invoice->items->sum('amount');
            $taxInCents = $invoice->tax ?? 0;
            $discountInCents = $invoice->discount ?? 0;
            $totalInCents = max(0, $subtotalInCents + $taxInCents - $discountInCents);
            
            $total = $totalInCents / 100;
            
            $paidInCents = $invoice->transactions->sum('amount');
            $creditsAppliedInCents = $invoice->credit_applied ?? 0;
            $paid = ($paidInCents + $creditsAppliedInCents) / 100;

            $balance = ($invoice->status === 'Paid') ? 0 : max(0, $total - $paid);
            $accentColor = $balance > 0 ? '#dc2626' : '#16a34a';
        @endphp
        <div
            class="relative flex flex-col min-h-[180px] bg-white border border-[#e2e8f0] rounded-2xl overflow-hidden shadow-sm">
            <div class="h-1 w-full" style="background-color: {{ $accentColor }}"></div>
            <div class="p-5 flex flex-col h-full">
                <div class="flex items-center gap-2 mb-4 border-b border-[#f1f5f9] pb-3">
                    <i data-lucide="banknotes" class="w-4 h-4 text-[#64748b]"></i>
                    <h2 class="text-[11px] font-bold text-[#64748b] uppercase tracking-widest">
                        {{ __('Financial Summary') }}</h2>
                </div>
                <div class="space-y-3 mb-auto">
                    <div class="flex justify-between items-center">
                        <span
                            class="text-[11px] text-[#64748b] font-semibold uppercase tracking-tight">{{ __('Total') }}</span>
                        <span class="text-xs text-[#334155] font-medium">{{ number_format($total, 2) }}
                            {{ strtoupper($invoice->currency) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span
                            class="text-[11px] text-[#64748b] font-semibold uppercase tracking-tight">{{ __('Paid') }}</span>
                        <span class="text-xs text-[#059669] font-semibold">{{ number_format($paid, 2) }}
                            {{ strtoupper($invoice->currency) }}</span>
                    </div>
                </div>
                <div class="flex justify-between items-center mt-4">
                    <span class="text-[11px] font-extrabold uppercase tracking-widest"
                        style="color: {{ $accentColor }}">{{ __('Balance Due') }}</span>
                    <span class="text-xl font-black" style="color: {{ $accentColor }}">{{ number_format($balance, 2) }}
                        {{ strtoupper($invoice->currency) }}</span>
                </div>
            </div>
            <div class="absolute -right-4 -bottom-4 w-16 h-16 rounded-full opacity-[0.03]"
                style="background-color: {{ $accentColor }}"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Invoice Content -->
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white rounded-[2rem] border border-[#E2E8F0] shadow-sm overflow-hidden">
                <div class="px-8 py-6 border-b border-[#E2E8F0] bg-[#F8FAFC]">
                    <h2 class="text-xs font-black text-[#64748B] uppercase tracking-widest">{{ __('Items Included') }}
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white border-b border-[#E2E8F0]">
                                <th
                                    class="px-8 py-4 text-[10px] font-black text-[#64748B] uppercase tracking-widest border-r border-[#f1f5f9]">
                                    {{ __('Description') }}
                                </th>
                                <th
                                    class="px-8 py-4 text-[10px] font-black text-[#64748B] uppercase tracking-widest text-right">
                                    {{ __('Amount') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td class="px-8 py-5 border-r border-[#f1f5f9]">
                                        <div class="font-bold text-[#1e293b]">{{ $item->description }}</div>
                                        <div class="text-[10px] text-[#94a3b8] mt-0.5 uppercase font-bold tracking-tighter">
                                            {{ $item->type }}</div>
                                    </td>
                                    <td class="px-8 py-5 text-right font-bold text-[#0F172A]">
                                        {{ $item->formatted_amount }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-8 bg-white border-t border-[#f1f5f9]">
                    <div class="flex justify-end">
                        <div class="w-full max-w-xs space-y-3">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-[#64748B] font-semibold">{{ __('Subtotal') }}</span>
                                <span class="text-[#334155] font-bold">{{ number_format($subtotalInCents / 100, 2) }}
                                    {{ strtoupper($invoice->currency) }}</span>
                            </div>
                            @if($taxInCents > 0)
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-[#64748B] font-semibold">{{ __('Tax') }}</span>
                                    <span class="text-[#334155] font-bold">{{ number_format($taxInCents / 100, 2) }}
                                        {{ strtoupper($invoice->currency) }}</span>
                                </div>
                            @endif
                            @if($discountInCents > 0)
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-[#64748B] font-semibold">{{ __('Discount') }}</span>
                                    <span class="text-[#dc2626] font-bold">- {{ number_format($discountInCents / 100, 2) }}
                                        {{ strtoupper($invoice->currency) }}</span>
                                </div>
                            @endif
                            <div class="pt-4 border-t-2 border-[#f1f5f9] flex justify-between items-center">
                                <span
                                    class="text-[#0F172A] text-xl font-black uppercase tracking-tighter">{{ __('Final Total') }}</span>
                                <span class="text-[#1E7CCF] text-2xl font-black">{{ number_format($totalInCents / 100, 2) }} {{ strtoupper($invoice->currency) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($invoice->notes)
                <div class="bg-white p-8 rounded-[2rem] border border-[#E2E8F0] shadow-sm">
                    <h2
                        class="text-[11px] font-black text-[#64748B] uppercase tracking-widest mb-4 border-b border-[#f1f5f9] pb-3">
                        {{ __('Important Notes') }}</h2>
                    <p class="text-[#475569] font-medium leading-relaxed text-sm">
                        {{ $invoice->notes }}
                    </p>
                </div>
            @endif

            {{-- Transactions --}}
            @if($invoice->transactions->isNotEmpty())
                <div id="detailed-transactions"
                    class="bg-white rounded-[2rem] border border-[#E2E8F0] shadow-sm overflow-hidden">
                    <div class="px-8 py-5 border-b border-[#E2E8F0] bg-[#f8fafc]">
                        <h2 class="text-xs font-black text-[#64748b] uppercase tracking-widest">
                            {{ __('Detailed Transaction Log') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#fcfdfe] border-b border-[#eff3f6]">
                                    <th class="px-6 py-3 text-[10px] font-bold text-[#64748B] uppercase tracking-widest">
                                        {{ __('Date / Time') }}</th>
                                    <th class="px-6 py-3 text-[10px] font-bold text-[#64748B] uppercase tracking-widest">
                                        {{ __('Method') }}</th>
                                    <th class="px-6 py-3 text-[10px] font-bold text-[#64748B] uppercase tracking-widest">
                                        {{ __('Transaction ID') }}</th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-bold text-[#64748B] uppercase tracking-widest text-right">
                                        {{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#f1f5f9]">
                                @foreach($invoice->transactions as $txn)
                                    <tr>
                                        <td class="px-6 py-4 text-xs font-medium text-[#475569]">
                                            {{ $txn->date->format('M d, Y - H:i') }}</td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-2 py-0.5 bg-[#f8fafc] border border-[#f1f5f9] rounded text-[10px] font-bold text-[#64748b] uppercase">{{ $txn->payment_method }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-mono text-[#94a3b8]">{{ $txn->transaction_id ?? '-' }}
                                        </td>
                                        <td
                                            class="px-6 py-4 text-sm text-right font-black @if($txn->amount > 0) text-[#059669] @else text-[#dc2626] @endif">
                                            {{ $txn->amount > 0 ? '' : '' }}{{ number_format($txn->amount / 100, 2) }}
                                            {{ strtoupper($invoice->currency) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar / Payment Info -->
        <div class="space-y-6">
            <div class="bg-white p-8 rounded-[2rem] border border-[#E2E8F0] shadow-sm">
                <h2 class="text-lg font-black text-[#0F172A] tracking-tight mb-6 flex items-center gap-2">
                    <i data-lucide="shield-check" class="w-5 h-5 text-[#1E7CCF]"></i>
                    {{ __('Payment Status') }}
                </h2>

                @if($invoice->status === 'Paid')
                    <div class="flex items-center gap-4 p-5 bg-green-50 rounded-2xl border border-green-100">
                        <div
                            class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center shrink-0 shadow-lg shadow-green-200">
                            <i data-lucide="check" class="text-white w-6 h-6"></i>
                        </div>
                        <div>
                            <div class="text-green-800 font-black text-sm">{{ __('Invoice Fully Paid') }}</div>
                            <div class="text-green-600 text-[10px] font-bold uppercase tracking-widest mt-0.5">
                                {{ __('Applied on') }} {{ $invoice->paid_at?->format('M d, Y') }}</div>
                        </div>
                    </div>
                @elseif($invoice->status === 'Unpaid' || $invoice->status === 'Partially Paid')
                    <div class="flex items-center gap-4 p-5 bg-orange-50 rounded-2xl border border-orange-100 mb-6">
                        <div>
                            <div class="text-orange-800 font-black text-sm">{{ __('Payment Pending') }}</div>
                            <div class="text-orange-600 text-[10px] font-bold uppercase tracking-widest mt-0.5">
                                {{ __('Due by') }} {{ $invoice->due_date?->format('M d, Y') }}</div>
                        </div>
                    </div>

                    <div class="mb-6 px-5 py-4 bg-gradient-to-br from-[#F8FAFC] to-[#F1F5F9] border border-[#E2E8F0] rounded-2xl relative overflow-hidden group">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 bg-blue-100 text-[#1E7CCF] rounded-lg flex items-center justify-center shadow-sm">
                                <i data-lucide="wallet" class="w-4 h-4"></i>
                            </div>
                            <span class="text-[10px] text-[#64748B] font-black uppercase tracking-[0.1em]">{{ __('Your Available Credit') }}</span>
                        </div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-3xl font-black text-[#0F172A]">{{ number_format(($invoice->user->balance ?? 0) / 100, 2) }}</span>
                            <span class="text-sm font-extrabold text-[#64748B] tracking-tight">{{ strtoupper($invoice->currency) }}</span>
                        </div>
                    </div>

                    <form wire:submit.prevent="applyCredit" class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-widest ml-1">{{ __('Apply Credit to Invoice') }}</label>
                            <div class="relative group">
                                <input type="number" step="0.01" wire:model.defer="applyAmount"
                                    class="w-full pl-16 pr-4 py-4 rounded-2xl border-2 border-[#F1F5F9] focus:border-[#1E7CCF] focus:ring-0 transition-all font-black text-[#0F172A] placeholder-[#CBD5E1]"
                                    placeholder="0.00">
                            </div>
                        </div>
                        <button type="submit"
                            class="w-full py-4 bg-[#1E7CCF] hover:bg-[#1669B2] text-white rounded-2xl shadow-lg shadow-blue-100 transition-all font-black uppercase tracking-widest text-xs flex items-center justify-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                            {{ __('Apply Credit Now') }}
                        </button>
                    </form>
                @endif
            </div>

            <div
                class="bg-[#0F172A] p-8 rounded-[2rem] border border-[#1e293b] shadow-xl text-white relative overflow-hidden group">
                <div class="relative z-10">
                    <h3 class="font-black text-blue-400 uppercase tracking-widest text-[10px] mb-4 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                        {{ __('Need Support?') }}
                    </h3>
                    <p class="text-sm text-slate-300 font-bold mb-6 leading-relaxed">
                        {{ __('If you have any questions regarding this invoice or need to discuss payment terms, our team is here to help.') }}
                    </p>
                    <a href="{{ route('portal.support') }}"
                        class="inline-flex items-center gap-3 px-6 py-3 bg-[#1E7CCF] hover:bg-[#1669B2] text-white rounded-xl transition-all text-xs font-black uppercase tracking-widest shadow-lg shadow-blue-500/20">
                        {{ __('Open Support Ticket') }}
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
                <i data-lucide="help-circle" class="absolute -right-4 -bottom-4 w-32 h-32 text-white opacity-[0.03] -rotate-12 group-hover:rotate-0 transition-transform duration-1000"></i>
            </div>
        </div>
    </div>
</div>