<div class="space-y-10" x-data="{ showModal: false }">
    <!-- 1. HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">{{ __('Support Center') }}</h1>
            <p class="text-[#64748B] font-medium mt-1">{{ __('Manage your tickets or reach out to our team.') }}</p>
        </div>
        <!-- NEW ACTION BUTTON -->
        <button @click="showModal = true"
            class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-5 h-5"></i> {{ __('Open New Ticket') }}
        </button>
    </div>

    <!-- 2. NEW TICKET LIST SECTION -->
    <div class="bg-white rounded-[2.5rem] border border-[#E2E8F0] shadow-sm overflow-hidden">
        <div class="px-8 py-6 border-b border-[#F8FAFC]">
            <h3 class="text-xl font-black text-[#0F172A]">{{ __('My Recent Tickets') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Ticket ID') }}</th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Subject') }}</th>
                        <th class="px-8 py-5 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Department') }}</th>
                        <th
                            class="px-8 py-5 text-[11px] font-black text-[#64748B] uppercase tracking-widest text-center">
                            {{ __('Status') }}</th>
                        <th
                            class="px-8 py-5 text-[11px] font-black text-[#64748B] uppercase tracking-widest text-right">
                            {{ __('Last Updated') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse($tickets as $ticket)
                        <tr class="hover:bg-[#F8FAFC] transition-colors cursor-pointer group"
                            onclick="window.location.href='{{ route('portal.support.show', $ticket) }}'">
                            <td class="px-8 py-6 bg-[#F8FAFC] border-r border-[#F1F5F9]">
                                <span
                                    class="inline-flex items-center px-3 py-1 bg-[#E9F2FB] text-[#1E7CCF] rounded-lg font-mono text-[11px] font-black border border-[#1E7CCF]/20 shadow-sm group-hover:bg-[#1E7CCF] group-hover:text-white transition-all">
                                    #{{ $ticket->ticket_number }}
                                </span>
                            </td>
                            <td class="px-8 py-5">
                                <p class="font-bold text-[#0F172A]">{{ $ticket->subject }}</p>
                            </td>
                            <td class="px-8 py-6">
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest {{ $ticket->getCategoryBadgeClasses() }}">
                                    {{ $ticket->category }}
                                </span>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <div class="flex justify-center">
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tighter {{ $ticket->status->badgeClasses() }}">
                                        <span class="w-1.5 h-1.5 rounded-full mr-1.5 bg-current opacity-70"></span>
                                        {{ $ticket->status->label() }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-8 py-6 text-right bg-[#F8FAFC] border-l border-[#F1F5F9]">
                                <div class="flex flex-col items-end">
                                    <!-- Highlighted Time Ago in a soft Indigo -->
                                    <span
                                        class="text-xs font-black text-[#6366F1] group-hover:text-[#1E7CCF] transition-colors">
                                        {{ $ticket->updated_at->diffForHumans() }}
                                    </span>
                                    <!-- Muted technical date string -->
                                    <span
                                        class="text-[11px] font-extrabold text-[#94A3B8] uppercase tracking-tighter mt-1">
                                        {{ $ticket->updated_at->format('M d, Y • H:i') }}
                                    </span>
                                </div>
                            </td>
                            {{-- <td class="px-8 py-6 text-right text-sm font-medium text-[#64748B]">
                                {{ $ticket->updated_at->format('l, F jS, Y (H:i)') }}
                            </td> --}}
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-8 py-12 text-center text-[#94A3B8] italic">
                                {{ __('No support tickets found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- SINGLE UNIFIED PAGINATION SECTION -->
        <div
            class="px-8 py-6 bg-[#F8FAFC] border-t border-[#E2E8F0] flex flex-col md:flex-row items-center justify-between gap-4">

            <!-- 1. Left Side: Showing results (Keep this one) -->
            <div class="text-sm text-[#64748B] font-medium">
                {{ __('Showing') }} {{ $tickets->firstItem() ?? 0 }} {{ __('to') }}
                {{ $tickets->lastItem() ?? 0 }} {{ __('of') }} {{ $tickets->total() }} {{ __('results') }}
            </div>

            <!-- 2. Middle: Per Page Selector -->
            <div class="flex items-center bg-white border border-[#E2E8F0] rounded-xl overflow-hidden shadow-sm">
                <span
                    class="px-4 py-2 text-[10px] font-black text-[#64748B] uppercase tracking-widest border-r border-[#E2E8F0] bg-[#F8FAFC]">
                    {{ __('Per page') }}
                </span>
                <select wire:model.live="perPage"
                    class="pl-4 pr-10 py-1.5 border-none text-sm font-bold text-[#0F172A] focus:ring-0 cursor-pointer">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                </select>
            </div>

            <!-- 3. Right Side: Navigation Buttons ONLY -->
            <div class="pagination-simple">
                {{ $tickets->links() }}
            </div>
        </div>

        <style>
            /* This CSS removes the extra "Showing results" text that Laravel puts inside the pagination buttons */
            .pagination-simple nav div:first-child {
                display: none !important;
            }
        </style>
    </div>

    <!-- START OF YOUR ORIGINAL DESIGN (STAYED THE SAME) -->

    <!-- CONTACT HELP CARD -->
    <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm flex items-center gap-6">
        <div class="w-14 h-14 bg-[#E9F2FB] text-[#1E7CCF] rounded-2xl flex items-center justify-center shrink-0">
            <i data-lucide="message-square" class="w-7 h-7"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-[#0F172A]">{{ __('Direct Assistance') }}</h3>
            <p class="text-sm text-[#64748B] mt-1">
                {{ __('Please include your Job ID and filename when contacting us so we can assist you faster.') }}
            </p>
        </div>
    </div>

    <!-- SUPPORT CHANNELS GRID -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Email Support -->
        <div
            class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm group hover:border-[#1E7CCF] transition-all">
            <p class="text-[#64748B] text-xs font-bold uppercase tracking-widest mb-4">{{ __('Email Support') }}</p>
            @if ($supportEmail)
                <div class="flex items-center gap-3">
                    <i data-lucide="mail" class="text-[#1E7CCF] w-5 h-5"></i>
                    <a href="mailto:{{ $supportEmail }}"
                        class="text-xl font-black text-[#0F172A] hover:text-[#1E7CCF] transition-colors">{{ $supportEmail }}</a>
                </div>
            @endif
        </div>

        <!-- Support Portal -->
        <div
            class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm group hover:border-[#1E7CCF] transition-all text-left">
            <p class="text-[#64748B] text-xs font-bold uppercase tracking-widest mb-4">{{ __('Knowledge Base') }}</p>
            @if ($supportUrl)
                <a href="{{ $supportUrl }}" target="_blank" rel="noopener"
                    class="inline-flex items-center gap-3 text-xl font-black text-[#0F172A] hover:text-[#1E7CCF] transition-colors">
                    {{ __('Open support portal') }} <i data-lucide="external-link" class="w-5 h-5"></i>
                </a>
            @endif
        </div>
    </div>

    <!-- CHECKLIST SECTION -->
    <div class="bg-[#F1F5F9] rounded-[2.5rem] border border-[#E2E8F0] p-10">
        <div class="flex items-center gap-3 mb-8">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-[#1E7CCF] shadow-sm">
                <i data-lucide="clipboard-check" class="w-6 h-6"></i>
            </div>
            <h4 class="text-xl font-black text-[#0F172A]">{{ __('CSV Format Checklist') }}</h4>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl border border-white shadow-sm flex items-center gap-4">
                <i data-lucide="check-circle-2" class="text-[#16A34A] w-5 h-5"></i>
                <span class="font-bold text-[#334155] text-sm">{{ __('One email per line') }}</span>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-white shadow-sm flex items-center gap-4">
                <i data-lucide="check-circle-2" class="text-[#16A34A] w-5 h-5"></i>
                <span class="font-bold text-[#334155] text-sm">{{ __('UTF-8 encoded file') }}</span>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-white shadow-sm flex items-center gap-4">
                <i data-lucide="check-circle-2" class="text-[#16A34A] w-5 h-5"></i>
                <span class="font-bold text-[#334155] text-sm">{{ __('Header row optional') }}</span>
            </div>
        </div>
    </div>

    <!-- 3. NEW TICKET MODAL -->
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-6 bg-black/50" x-cloak
        @click.self="showModal = false">
        <div class="bg-white w-full max-w-2xl rounded-[2.5rem] p-10 shadow-2xl overflow-y-auto max-h-[90vh]"
            @click.stop>
            <h2 class="text-2xl font-black text-[#0F172A] mb-8">{{ __('Create New Ticket') }}</h2>
            <form wire:submit.prevent="createTicket" class="space-y-6">
                <!-- Subject -->
                <div>
                    <label class="block text-sm font-bold text-[#334155] mb-2">Subject</label>
                    <input type="text" wire:model="subject"
                        class="w-full rounded-xl border-[#E2E8F0] focus:ring-[#1E7CCF]">
                    @error('subject')
                        <span class="text-red-500 text-xs font-bold mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-bold text-[#334155] mb-2">Category</label>
                        <select wire:model="category" class="w-full rounded-xl border-[#E2E8F0]">
                            <option value="Technical">Technical</option>
                            <option value="Billing">Billing</option>
                            <option value="Sales">Sales</option>
                        </select>
                        @error('category')
                            <span class="text-red-500 text-xs font-bold mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                    <!-- Priority -->
                    <div>
                        <label class="block text-sm font-bold text-[#334155] mb-2">Priority</label>
                        <select wire:model="priority" class="w-full rounded-xl border-[#E2E8F0]">
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                        @error('priority')
                            <span class="text-red-500 text-xs font-bold mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- Message -->
                <div>
                    <label class="block text-sm font-bold text-[#334155] mb-2">Detailed Message</label>
                    <textarea wire:model="message" rows="5" class="w-full rounded-xl border-[#E2E8F0]"
                        placeholder="Describe your problem..."></textarea>
                    @error('message')
                        <span class="text-red-500 text-xs font-bold mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Attachment -->
                <div>
                    <label class="block text-sm font-bold text-[#334155] mb-2">Attachment (Optional)</label>
                    <input type="file" wire:model="attachment" class="text-sm">
                    @error('attachment')
                        <span class="text-red-500 text-xs font-bold mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-4 pt-4">
                    <button type="button" @click="showModal = false"
                        class="px-6 py-3 font-bold text-[#64748B]">{{ __('Cancel') }}</button>
                    <button type="submit"
                        class="bg-[#1E7CCF] text-white px-10 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all">
                        <span wire:loading.remove wire:target="createTicket">{{ __('Open Ticket') }}</span>
                        <span wire:loading wire:target="createTicket">Processing...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
