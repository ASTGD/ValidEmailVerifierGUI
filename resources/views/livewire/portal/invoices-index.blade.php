<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">{{ __('Invoices') }}</h1>
            <p class="text-[#64748B] font-medium mt-1">
                {{ __('View and manage your invoices and payments.') }}
            </p>
        </div>
        <div class="px-6 py-3 bg-white rounded-xl border border-[#E2E8F0] shadow-sm font-bold text-[#0F172A]">
            {{ __('Credit Balance:') }}
            <span class="text-[#1E7CCF]">
                @php
                    $currency = strtoupper(auth()->user()->currency ?: 'USD');
                    $prefix = $currency === 'USD' ? '$' : $currency . ' ';
                @endphp
                {{ $prefix }}{{ number_format(auth()->user()->balance / 100, 2) }}
            </span>
        </div>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-[#E2E8F0] flex items-center gap-4">
        <div class="flex items-center gap-3 px-4 py-2 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
            <i data-lucide="filter" class="w-4 h-4 text-[#64748B]"></i>
            <select wire:model.live="status"
                class="bg-transparent border-none text-sm font-bold text-[#334155] focus:ring-0 cursor-pointer">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="Paid">{{ __('Paid') }}</option>
                <option value="Unpaid">{{ __('Unpaid') }}</option>
                <option value="Cancelled">{{ __('Cancelled') }}</option>
                <option value="Refunded">{{ __('Refunded') }}</option>
                <option value="Collections">{{ __('Collections') }}</option>
            </select>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] border border-[#E2E8F0] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Invoice #') }}
                        </th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Date') }}
                        </th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Due Date') }}
                        </th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Total') }}
                        </th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Status') }}
                        </th>
                        <th
                            class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest text-right">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse($this->invoices as $invoice)
                        <tr class="hover:bg-[#F8FAFC] transition-colors">
                            <td class="px-8 py-5">
                                <div class="font-bold text-[#0F172A]">{{ $invoice->invoice_number }}</div>
                            </td>
                            <td class="px-8 py-5 text-sm font-medium text-[#334155]">
                                {{ $invoice->date?->format('M d, Y') }}
                            </td>
                            <td class="px-8 py-5 text-sm font-medium text-[#334155]">
                                {{ $invoice->due_date?->format('M d, Y') }}
                            </td>
                            <td class="px-8 py-5 text-sm font-bold text-[#0F172A]">
                                {{ $invoice->formatted_total }}
                            </td>
                            <td class="px-8 py-5">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase
                                                                                            @if($invoice->status === 'Paid') bg-green-100 text-green-700
                                                                                            @elseif($invoice->status === 'Unpaid') bg-yellow-100 text-yellow-700
                                                                                            @elseif($invoice->status === 'Cancelled') bg-red-100 text-red-700
                                                                                            @else bg-gray-100 text-gray-700 @endif">
                                    {{ $invoice->status }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right space-x-2">
                                <a href="{{ route('portal.invoices.show', $invoice->id) }}"
                                    class="text-[#64748B] hover:text-[#1E7CCF] transition-colors inline-block"
                                    title="{{ __('View Invoice') }}">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                <button wire:click="download('{{ $invoice->id }}')"
                                    class="text-[#64748B] hover:text-[#1E7CCF] transition-colors"
                                    title="{{ __('Download PDF') }}">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-8 py-16 text-center">
                                <div class="max-w-xs mx-auto">
                                    <i data-lucide="file-text" class="w-12 h-12 text-[#CBD5E1] mx-auto mb-4"></i>
                                    <h3 class="text-lg font-bold text-[#0F172A]">{{ __('No invoices found') }}</h3>
                                    <p class="text-sm text-[#64748B] mt-1">
                                        {{ __('You have not generated any invoices yet.') }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-8 py-6 bg-[#F8FAFC] border-t border-[#E2E8F0]">
            {{ $this->invoices->links() }}
        </div>
    </div>
</div>