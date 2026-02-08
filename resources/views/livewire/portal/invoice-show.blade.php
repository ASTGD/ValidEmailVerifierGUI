<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('portal.invoices.index') }}" class="text-sm font-bold text-[#1E7CCF] hover:underline flex items-center gap-1">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    {{ __('Back to Invoices') }}
                </a>
            </div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">
                {{ __('Invoice') }} #{{ $invoice->invoice_number }}
            </h1>

            @if(session('status'))
                <div class="mt-4 p-4 rounded-lg bg-green-50 border border-green-100 text-green-800 font-bold">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="mt-4 p-4 rounded-lg bg-red-50 border border-red-100 text-red-800 font-bold">{{ session('error') }}</div>
            @endif
            <p class="text-[#64748B] font-medium mt-1">
                {{ __('Issued on') }} {{ $invoice->date?->format('M d, Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="download" class="flex items-center gap-2 px-6 py-3 bg-[#1E7CCF] hover:bg-[#1669B2] text-white rounded-xl shadow-lg transition-all font-bold">
                <i data-lucide="download" class="w-4 h-4"></i>
                {{ __('Download PDF') }}
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Invoice Content -->
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white rounded-[2.5rem] border border-[#E2E8F0] shadow-sm overflow-hidden">
                <div class="p-8 border-b border-[#E2E8F0] bg-[#F8FAFC]">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-sm font-black text-[#64748B] uppercase tracking-widest mb-4">{{ __('Billed To') }}</h2>
                            <div class="font-bold text-[#0F172A] text-lg">{{ $invoice->user->name }}</div>
                            <div class="text-[#334155] mt-1">{{ $invoice->user->email }}</div>
                            @if($invoice->user->address_1)
                                <div class="text-[#334155] text-sm mt-2">
                                    {{ $invoice->user->address_1 }}<br>
                                    @if($invoice->user->address_2){{ $invoice->user->address_2 }}<br>@endif
                                    {{ $invoice->user->city }}, {{ $invoice->user->state }} {{ $invoice->user->postcode }}<br>
                                    {{ $invoice->user->country }}
                                </div>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-black uppercase
                                @if($invoice->status === 'Paid') bg-green-100 text-green-700
                                @elseif($invoice->status === 'Unpaid') bg-yellow-100 text-yellow-700
                                @elseif($invoice->status === 'Cancelled') bg-red-100 text-red-700
                                @else bg-gray-100 text-gray-700 @endif">
                                {{ $invoice->status }}
                            </span>
                            <div class="mt-4">
                                <h2 class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">{{ __('Due Date') }}</h2>
                                <div class="font-bold text-[#0F172A]">{{ $invoice->due_date?->format('F d, Y') ?: '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white border-b border-[#E2E8F0]">
                                <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                                    {{ __('Description') }}
                                </th>
                                <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest text-right">
                                    {{ __('Amount') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td class="px-8 py-6">
                                        <div class="font-bold text-[#0F172A]">{{ $item->description }}</div>
                                        <div class="text-xs text-[#64748B] mt-1 uppercase font-medium">{{ $item->type }}</div>
                                    </td>
                                    <td class="px-8 py-6 text-right font-bold text-[#0F172A]">
                                        {{ $item->formatted_amount }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-8 bg-[#F8FAFC]">
                    <div class="flex justify-end">
                        <div class="w-full max-w-xs space-y-4">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-[#64748B] font-bold">{{ __('Subtotal') }}</span>
                                <span class="text-[#0F172A] font-bold">{{ number_format($invoice->subtotal / 100, 2) }} {{ strtoupper($invoice->currency) }}</span>
                            </div>
                            <div class="pt-4 border-t border-[#E2E8F0] flex justify-between items-center">
                                <span class="text-[#0F172A] text-xl font-black">{{ __('Total') }}</span>
                                <span class="text-[#1E7CCF] text-2xl font-black">{{ $invoice->formatted_total }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($invoice->notes)
                <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm">
                    <h2 class="text-[11px] font-black text-[#64748B] uppercase tracking-widest mb-4">{{ __('Notes') }}</h2>
                    <p class="text-[#334155] font-medium leading-relaxed">
                        {{ $invoice->notes }}
                    </p>
                </div>
            @endif

            {{-- Transactions --}}
            @if($invoice->transactions->isNotEmpty())
                <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm">
                    <h2 class="text-[11px] font-black text-[#64748B] uppercase tracking-widest mb-4">{{ __('Transactions') }}</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white border-b border-[#E2E8F0]">
                                    <th class="px-4 py-2 text-xs font-black text-[#64748B]">{{ __('Date') }}</th>
                                    <th class="px-4 py-2 text-xs font-black text-[#64748B]">{{ __('Method') }}</th>
                                    <th class="px-4 py-2 text-xs font-black text-[#64748B]">{{ __('Transaction ID') }}</th>
                                    <th class="px-4 py-2 text-xs font-black text-[#64748B] text-right">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E8F0]">
                                @foreach($invoice->transactions as $txn)
                                    <tr>
                                        <td class="px-4 py-3 text-sm">{{ $txn->date->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $txn->payment_method }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $txn->transaction_id ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-right">{{ number_format($txn->amount / 100, 2) }} {{ strtoupper($invoice->currency) }}</td>
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
            <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm">
                <h2 class="text-lg font-black text-[#0F172A] tracking-tight mb-6">{{ __('Payment Status') }}</h2>

                @if($invoice->status === 'Paid')
                    <div class="flex items-center gap-4 p-4 bg-green-50 rounded-2xl border border-green-100">
                        <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center shrink-0">
                            <i data-lucide="check" class="text-white w-6 h-6"></i>
                        </div>
                        <div>
                            <div class="text-green-800 font-bold">{{ __('Invoice Paid') }}</div>
                            <div class="text-green-600 text-xs font-medium">{{ __('On') }} {{ $invoice->paid_at?->format('M d, Y') }}</div>
                        </div>
                    </div>
                @elseif($invoice->status === 'Unpaid' || $invoice->status === 'Partially Paid')
                    <div class="flex items-center gap-4 p-4 bg-yellow-50 rounded-2xl border border-yellow-100 mb-6">
                        <div class="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center shrink-0">
                            <i data-lucide="clock" class="text-white w-6 h-6"></i>
                        </div>
                        <div>
                            <div class="text-yellow-800 font-bold">{{ __('Payment Pending') }}</div>
                            <div class="text-yellow-600 text-xs font-medium">{{ __('Due by') }} {{ $invoice->due_date?->format('M d, Y') }}</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="text-xs text-[#64748B] font-medium">{{ __('Available Credit') }}</div>
                        <div class="text-lg font-black">{{ number_format(($invoice->user->balance ?? 0) / 100, 2) }} {{ strtoupper($invoice->currency) }}</div>
                    </div>

                    <form wire:submit.prevent="applyCredit">
                        <label class="block text-xs text-[#64748B] mb-2">{{ __('Amount to apply') }}</label>
                        <input type="number" step="0.01" wire:model.defer="applyAmount" class="w-full px-4 py-3 rounded-xl border border-[#E2E8F0] mb-3" placeholder="0.00">
                        <div class="flex gap-3">
                            <button type="submit" class="flex-1 py-3 bg-[#1E7CCF] hover:bg-[#1669B2] text-white rounded-2xl shadow-lg transition-all font-black uppercase tracking-widest text-xs">{{ __('Apply Credit') }}</button>
                            <button type="button" onclick="@this.set('applyAmount', {{ min(($invoice->user->balance ?? 0) / 100, ($invoice->total - $invoice->transactions->sum('amount')) / 100) }})" class="py-3 px-4 bg-white rounded-2xl border border-[#E2E8F0] font-bold">{{ __('Max') }}</button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="bg-[#F8FAFC] p-8 rounded-[2.5rem] border border-[#E2E8F0]">
                <h3 class="font-black text-[#0F172A] uppercase tracking-widest text-[10px] mb-4">{{ __('Need Help?') }}</h3>
                <p class="text-sm text-[#64748B] font-medium mb-6">
                    {{ __('If you have any questions regarding this invoice, please contact our support team.') }}
                </p>
                <a href="{{ route('portal.support') }}" class="inline-flex items-center gap-2 text-sm font-bold text-[#1E7CCF] hover:underline">
                    {{ __('Open Support Ticket') }}
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </div>
</div>
