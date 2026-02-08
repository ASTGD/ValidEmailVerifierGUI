<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Left: Detailed Info Table -->
    <div class="lg:col-span-7 bg-white border border-gray-200 rounded shadow-sm overflow-hidden">
        <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex justify-between items-center">
            <span class="text-[11px] font-bold text-gray-600 uppercase tracking-wider">Invoice Details</span>
            <span class="text-[10px] text-gray-400 font-medium">#{{ $record->invoice_number }}</span>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="w-40 px-4 py-2.5 bg-gray-50/50 font-bold text-gray-500 border-r border-gray-100 italic">
                        Client Name</td>
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-gray-900">{{ $record->user->name ?? 'N/A' }}</span>
                            <a href="/admin/customers/{{ $record->user_id }}/edit"
                                class="text-blue-500 hover:text-blue-700 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14">
                                    </path>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 bg-gray-50/50 font-bold text-gray-500 border-r border-gray-100 italic">
                        Invoice Date</td>
                    <td class="px-4 py-2.5 font-medium text-gray-700">{{ $record->date?->format('d/m/Y') ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 bg-gray-50/50 font-bold text-gray-500 border-r border-gray-100 italic">Due
                        Date</td>
                    <td class="px-4 py-2.5 font-medium text-gray-700">{{ $record->due_date?->format('d/m/Y') ?? '-' }}
                    </td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 bg-gray-50/50 font-bold text-gray-500 border-r border-gray-100 italic">Total
                        Amount</td>
                    <td class="px-4 py-2.5 text-lg font-black text-gray-900 tracking-tight">
                        {{ $record->formatted_total }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 bg-gray-50/50 font-bold text-gray-500 border-r border-gray-100 italic">
                        Balance Due</td>
                    <td
                        class="px-4 py-2.5 text-lg font-black text-red-600 underline underline-offset-4 decoration-2 tabular-nums">
                        {{ $record->formatted_total }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Right: Status and Control Panel -->
    <div class="lg:col-span-5 flex flex-col gap-4">
        <!-- Status Badge Panel -->
        @php
            $statusConfigs = [
                'Unpaid' => 'bg-red-50 text-red-700 border-red-100',
                'Paid' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                'Cancelled' => 'bg-gray-50 text-gray-600 border-gray-100',
                'Refunded' => 'bg-blue-50 text-blue-700 border-blue-100',
            ];
            $style = $statusConfigs[$record->status] ?? $statusConfigs['Unpaid'];
        @endphp

        <div
            class="flex-1 border {{ $style }} rounded p-6 flex flex-col items-center justify-center text-center shadow-sm">
            <div class="text-[10px] font-black uppercase tracking-[0.2em] opacity-60 mb-1">Current Status</div>
            <div class="text-3xl font-black uppercase tracking-widest leading-none mb-4">{{ $record->status }}</div>

            <div class="w-full max-w-[240px] pt-4 border-t border-current/10 space-y-3">
                <div class="flex gap-1">
                    <select
                        class="flex-1 text-[11px] font-bold border-gray-200 rounded ring-0 focus:ring-1 focus:ring-blue-400 py-1.5 px-2 bg-white">
                        <option>Invoice Created</option>
                    </select>
                    <button
                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-tighter transition">Notify</button>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    @if($record->status !== 'Paid')
                        <button
                            class="col-span-2 bg-emerald-600 hover:bg-emerald-700 text-white py-2 rounded text-[11px] font-black uppercase tracking-widest shadow-lg shadow-emerald-200/50 transition active:translate-y-0.5">Attempt
                            Payment</button>
                    @endif
                    <button
                        class="bg-white hover:bg-red-50 text-gray-600 hover:text-red-600 border border-gray-200 py-1.5 rounded text-[10px] font-black uppercase tracking-tighter transition">Cancel</button>
                    <button
                        class="bg-white hover:bg-blue-50 text-gray-600 hover:text-blue-600 border border-gray-200 py-1.5 rounded text-[10px] font-black uppercase tracking-tighter transition">Set
                        Unpaid</button>
                </div>
            </div>
        </div>
    </div>
</div>