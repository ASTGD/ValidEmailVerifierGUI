<div class="space-y-8" @if ($this->shouldPoll) wire:poll.8s @endif>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">{{ __('My Orders') }}</h1>
            <p class="text-[#64748B] font-medium mt-1">
                {{ __('Review orders, payment totals, and processing status.') }}</p>
        </div>
        <a href="{{ route('portal.upload') }}"
            class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all flex items-center gap-2"
            wire:navigate>
            <i data-lucide="plus" class="w-5 h-5"></i> {{ __('Upload a list') }}
        </a>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-[#E2E8F0] flex items-center gap-4">
        <div class="flex items-center gap-3 px-4 py-2 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
            <i data-lucide="filter" class="w-4 h-4 text-[#64748B]"></i>
            <select wire:model="status"
                class="bg-transparent border-none text-sm font-bold text-[#334155] focus:ring-0 cursor-pointer">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach (\App\Enums\VerificationOrderStatus::cases() as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] border border-[#E2E8F0] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">{{ __('Order') }}</th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">{{ __('Emails') }}</th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">{{ __('Total') }}</th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">{{ __('Status') }}</th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">{{ __('Created') }}</th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse($this->orders as $order)
                        <tr class="hover:bg-[#F8FAFC] transition-colors">
                            <td class="px-8 py-5">
                                <div class="font-bold text-[#0F172A]">{{ $order->original_filename }}</div>
                                <div class="text-[10px] text-[#94A3B8] font-mono">{{ $order->id }}</div>
                            </td>
                            <td class="px-8 py-5 text-sm font-medium text-[#334155]">
                                {{ number_format($order->email_count) }}
                            </td>
                            <td class="px-8 py-5 text-sm font-bold text-[#0F172A]">
                                @php
                                    $currencyPrefix = strtolower($order->currency) === 'usd' ? '$' : strtoupper($order->currency).' ';
                                @endphp
                                {{ $currencyPrefix }}{{ number_format($order->amount_cents / 100, 2) }}
                            </td>
                            <td class="px-8 py-5">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase {{ $order->status->badgeClasses() }}">
                                    {{ $order->status->label() }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-sm text-[#64748B]">
                                {{ $order->created_at?->format('M d, Y H:i') }}
                            </td>
                            <td class="px-8 py-5 text-right space-x-2">
                                @if ($order->job)
                                    <a href="{{ route('portal.jobs.show', $order->job) }}"
                                        class="inline-flex bg-[#F1F5F9] hover:bg-[#1E7CCF] hover:text-white text-[#334155] px-4 py-2 rounded-lg text-[11px] font-black uppercase transition-all"
                                        wire:navigate>
                                        {{ __('View Job') }}
                                    </a>
                                @else
                                    <span class="text-xs text-[#94A3B8]">{{ __('Pending') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-8 py-16 text-center">
                                <div class="max-w-xs mx-auto">
                                    <i data-lucide="inbox" class="w-12 h-12 text-[#CBD5E1] mx-auto mb-4"></i>
                                    <h3 class="text-lg font-bold text-[#0F172A]">{{ __('No orders yet') }}</h3>
                                    <p class="text-sm text-[#64748B] mt-1">{{ __('Upload a list to create your first order.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-8 py-6 bg-[#F8FAFC] border-t border-[#E2E8F0]">
            {{ $this->orders->links() }}
        </div>
    </div>
</div>
