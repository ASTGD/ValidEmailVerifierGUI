<div class="space-y-8">
    <!-- 1. HEADER SECTION (With Meta Info) -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="flex items-center gap-5">
            <!-- Back Button -->
            <a href="{{ route('portal.support') }}"
                class="w-12 h-12 bg-white border border-[#E2E8F0] rounded-2xl flex items-center justify-center text-[#64748B] hover:text-[#1E7CCF] transition-all shadow-sm group"
                wire:navigate>
                <i data-lucide="arrow-left" class="w-6 h-6 group-hover:-translate-x-1 transition-transform"></i>
            </a>
            <div>
                <h1 class="text-3xl font-black text-[#0F172A] tracking-tight leading-tight">{{ $ticket->subject }}</h1>
            </div>
        </div>

        <!-- Right Side Badges -->
        <div class="flex items-center gap-3">
            <!-- Priority Badge -->
            <div class="px-4 py-2 bg-white border border-[#E2E8F0] rounded-xl flex items-center gap-2 shadow-sm">
                <span
                    class="text-[10px] font-black text-[#94A3B8] uppercase tracking-widest">{{ __('Priority') }}:</span>
                <span class="text-xs font-black text-[#0F172A] uppercase">{{ $ticket->priority->label() }}</span>
            </div>
            <!-- Status Badge (Dynamic Color) -->
            <div
                class="px-4 py-2 rounded-xl font-black text-[10px] uppercase tracking-widest shadow-sm {{ $ticket->status->badgeClasses() }}">
                {{ $ticket->status->label() }}
            </div>
        </div>
    </div>

    <!-- 2. TICKET INFO SUMMARY (Like the Admin "Workflow" section) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-sm">
            <p class="text-[10px] font-black text-[#94A3B8] uppercase tracking-widest mb-1">{{ __('Ticket ID') }}:</p>
            <p class="text-sm font-bold text-[#334155]">#{{ $ticket->ticket_number }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-sm">
            <p class="text-[10px] font-black text-[#94A3B8] uppercase tracking-widest mb-1">{{ __('Department') }}</p>
            <p class="text-sm font-bold text-[#334155]">{{ $ticket->category }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-sm">
            <p class="text-[10px] font-black text-[#94A3B8] uppercase tracking-widest mb-1">{{ __('Created') }}</p>
            <p class="text-sm font-bold text-[#334155]">{{ $ticket->created_at->format('l, F jS, Y (H:i)') }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-sm">
            <p class="text-[10px] font-black text-[#94A3B8] uppercase tracking-widest mb-1">{{ __('Last Activity') }}
            </p>
            <p class="text-sm font-bold text-[#334155]">{{ $ticket->updated_at->diffForHumans() }}</p>
        </div>
        <!-- Spacer for larger screens -->
        <div class="hidden md:block col-span-2"></div>
    </div>

    <!-- 3. CHAT MESSAGES THREAD -->
    <div class="grid grid-cols-3 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm p-6 md:p-10 flex flex-col space-y-10">
            @foreach ($messages as $msg)
                <div class="flex {{ $msg->is_admin ? 'justify-start' : 'justify-end' }}">
                    <div class="max-w-[85%] md:max-w-[70%] space-y-2">

                        <!-- Message Bubble -->
                        <div
                            class="relative p-6 rounded-3xl {{ $msg->is_admin ? 'bg-[#1E7CCF] text-white rounded-tl-none' : 'bg-[#E9F2FB] text-[#0F172A] border border-[#1E7CCF]/10 rounded-tr-none' }}">
                            <p class="text-sm md:text-base font-medium leading-relaxed">{{ $msg->content }}</p>

                            @if ($msg->attachment)
                                <div
                                    class="mt-4 pt-4 border-t {{ $msg->is_admin ? 'border-gray-200' : 'border-blue-200' }}">
                                    <a href="{{ Storage::url($msg->attachment) }}" target="_blank"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-black/5 text-xs font-bold text-[#1E7CCF] hover:shadow-md transition-all">
                                        <i data-lucide="image" class="w-4 h-4"></i> {{ __('View Attachment') }}
                                    </a>
                                </div>
                            @endif
                        </div>

                        <!-- Message Meta -->
                        <div
                            class="flex items-center gap-2 px-2 {{ $msg->is_admin ? 'justify-start' : 'justify-end' }}">
                            @if ($msg->is_admin)
                                <span
                                    class="w-5 h-5 bg-[#1E7CCF] text-white rounded-full flex items-center justify-center">
                                    <i data-lucide="shield-check" class="w-3 h-3"></i>
                                </span>
                            @endif
                            <span class="text-[10px] font-black uppercase tracking-widest text-[#94A3B8]">
                                {{ $msg->is_admin ? __('Support Team') : __('You') }} •
                                {{ $msg->created_at->format('H:i A') }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div>
            <!-- TICKET INFO CARD -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm overflow-hidden">
                <div class="p-6 bg-[#F8FAFC] border-b border-[#E2E8F0]">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[#0F172A]">
                        {{ __('Ticket Information') }}</h4>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Name Info -->
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-[#F1F5F9] rounded-lg text-[#64748B]"><i data-lucide="user"
                                class="w-4 h-4"></i></div>
                        <div>
                            <p class="text-[9px] font-black text-[#94A3B8] uppercase tracking-[0.15em] mb-0.5">
                                {{ __('Customer Name') }}</p>
                            <p class="text-sm font-bold text-[#334155]">{{ $ticket->user->name }}</p>
                        </div>
                    </div>
                    <!-- Email Info -->
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-[#F1F5F9] rounded-lg text-[#64748B]"><i data-lucide="mail"
                                class="w-4 h-4"></i></div>
                        <div>
                            <p class="text-[9px] font-black text-[#94A3B8] uppercase tracking-[0.15em] mb-0.5">
                                {{ __('Email Address') }}</p>
                            <p class="text-sm font-bold text-[#334155] truncate max-w-[150px]">
                                {{ $ticket->user->email }}</p>
                        </div>
                    </div>
                    <!-- Subject Info -->
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-[#F1F5F9] rounded-lg text-[#64748B]"><i data-lucide="file-text"
                                class="w-4 h-4"></i></div>
                        <div>
                            <p class="text-[9px] font-black text-[#94A3B8] uppercase tracking-[0.15em] mb-0.5">
                                {{ __('Ticket ID') }}</p>
                            <p class="text-sm font-bold text-[#1E7CCF] font-mono">#{{ $ticket->ticket_number }}</p>
                        </div>
                    </div>
                    <hr class="border-[#F1F5F9]">
                </div>
            </div>
        </div>
    </div>

    <!-- 4. REPLY AREA -->
    @if ($ticket->status !== \App\Enums\SupportTicketStatus::Closed)
        <div class="bg-white rounded-xl border-2 border-[#E9F2FB] p-2 shadow-xl shadow-blue-900/5">
            <form wire:submit.prevent="sendMessage" class="relative">
                <textarea wire:model="message" rows="4"
                    class="w-full border-none focus:ring-0 p-8 text-sm md:text-base font-medium placeholder-[#94A3B8] rounded-[2rem]"
                    placeholder="Type your reply here..."></textarea>

                <div
                    class="flex flex-col md:flex-row items-center justify-between p-4 bg-[#F8FAFC] rounded-[2rem] gap-4">
                    <!-- File Upload -->
                    <div class="relative group">
                        <input type="file" wire:model="attachment" id="file-upload" class="hidden">
                        <label for="file-upload"
                            class="flex items-center gap-2 px-5 py-2.5 bg-white border border-[#E2E8F0] rounded-xl text-xs font-bold text-[#64748B] cursor-pointer hover:border-[#1E7CCF] hover:text-[#1E7CCF] transition-all">
                            <i data-lucide="paperclip" class="w-4 h-4"></i>
                            <span>{{ $attachment ? $attachment->getClientOriginalName() : __('Attach Screenshot') }}</span>
                        </label>
                    </div>

                    <!-- Send Button -->
                    <button type="submit" wire:loading.attr="disabled"
                        class="w-full md:w-auto bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-10 py-3.5 rounded-xl font-bold text-sm shadow-lg shadow-blue-100 flex items-center justify-center gap-2 transition-all">
                        <span wire:loading.remove wire:target="sendMessage">{{ __('Send Reply') }}</span>
                        <span wire:loading wire:target="sendMessage" class="flex items-center gap-2">
                            <i data-lucide="refresh-cw" class="w-4 h-4 animate-spin"></i> {{ __('Sending...') }}
                        </span>
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="p-8 text-center bg-[#F1F5F9] rounded-[2.5rem] border border-[#E2E8F0]">
            <p class="text-sm font-bold text-[#64748B] italic uppercase tracking-widest">
                {{ __('This ticket is closed. Please open a new one if you need further help.') }}</p>
        </div>
    @endif
</div>

<script>
    // Essential for Lucide icons to refresh on Livewire Navigate
    if (window.lucide) {
        lucide.createIcons();
    }
</script>
