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
            <div class="px-4 py-2 border rounded-xl flex items-center gap-2 shadow-sm {{ $ticket->priority->badgeClasses() }}">
                <span
                    class="text-[9px] font-black uppercase tracking-widest opacity-70">{{ __('Priority') }}:</span>
                <span class="text-[10px] font-black uppercase">{{ $ticket->priority->label() }}</span>
            </div>
            <!-- Status Badge (Dynamic Color) -->
            <div
                class="px-4 py-2 border rounded-xl font-black text-[10px] uppercase tracking-widest shadow-sm {{ $ticket->status->badgeClasses() }}">
                {{ $ticket->status->label() }}
            </div>
        </div>
    </div>

    <!-- 2. TICKET INFO SUMMARY (Like the Admin "Workflow" section) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-sm">
            <p class="text-[10px] font-black text-[#94A3B8] uppercase tracking-widest mb-1">{{ __('Ticket ID') }}:</p>
            <p class="text-sm font-bold text-[#1E7CCF]">#{{ $ticket->ticket_number }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-sm">
            <p class="text-[10px] font-black text-[#94A3B8] uppercase tracking-widest mb-2">{{ __('Department') }}</p>
            <span class="inline-flex px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest {{ $ticket->getCategoryBadgeClasses() }}">
                {{ $ticket->category }}
            </span>
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

    <!-- 3. CHAT MESSAGES THREAD & TICKET INFO -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <!-- MESSAGE THREAD (Takes 2/3 of space) -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-[#E2E8F0] shadow-sm p-6 md:p-8 flex flex-col space-y-8">
            @foreach ($messages as $msg)
                <div class="flex {{ $msg->is_admin ? 'justify-start' : 'justify-end' }} gap-4">
                    
                    @if ($msg->is_admin)
                        <!-- Admin Avatar -->
                        <div class="flex-shrink-0 mt-6">
                            <div class="w-15 h-15 rounded-full bg-[#1E7CCF] flex items-center justify-center text-white shadow-md">
                                <i data-lucide="headphones" class="w-10 h-10"></i>
                            </div>
                        </div>
                    @endif

                    <div class="max-w-[85%] md:max-w-[75%] space-y-1">
                        <!-- Sender Name & Time -->
                        <div class="flex items-center gap-2 px-1 {{ $msg->is_admin ? 'justify-start' : 'justify-end' }}">
                            <span class="text-[10px] font-black uppercase tracking-widest text-[#94A3B8]">
                                {{ $msg->is_admin ? __('Support Team') : $ticket->user->name }}
                            </span>
                            <span class="text-[9px] text-[#CBD5E1]">•</span>
                            <span class="text-[10px] text-[#94A3B8]">{{ $msg->created_at->format('H:i A') }}</span>
                        </div>

                        <!-- Message Bubble -->
                        <div class="p-6 rounded-2xl shadow-sm {{ $msg->is_admin 
                            ? 'bg-[#1E7CCF] text-white rounded-tl-none' 
                            : 'bg-[#F8FAFC] text-[#334155] border border-[#E2E8F0] rounded-tr-none' }}">
                            
                            <p class="text-sm md:text-base font-medium leading-relaxed whitespace-pre-wrap">{{ $msg->content }}</p>

                            @if ($msg->attachment)
                                <div class="mt-4 pt-4 border-t {{ $msg->is_admin ? 'border-white/10' : 'border-[#E2E8F0]' }}">
                                    <a href="{{ Storage::url($msg->attachment) }}" target="_blank"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-xl backdrop-blur-sm border {{ $msg->is_admin ? 'border-white/10' : 'border-[#1E7CCF]/10' }} text-xs font-bold {{ $msg->is_admin ? 'text-white' : 'text-[#1E7CCF]' }} transition-all">
                                        <i data-lucide="file-image" class="w-4 h-4"></i> {{ __('View Attachment') }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if (!$msg->is_admin)
                        <!-- User Avatar -->
                        <div class="flex-shrink-0 mt-6">
                            <div class="w-15 h-15 rounded-full bg-white border-2 border-[#E2E8F0] flex items-center justify-center text-[#1E7CCF] shadow-sm">
                                <i data-lucide="user" class="w-10 h-10"></i>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- TICKET INFO CARD (Takes 1/3 of space) -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm overflow-hidden sticky top-8">
                <div class="p-6 bg-[#F8FAFC] border-b border-[#E2E8F0]">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[#0F172A] flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4 text-[#1E7CCF]"></i>
                        {{ __('Ticket Details') }}
                    </h4>
                </div>
                <div class="p-6 space-y-8">
                    <!-- Customer Info -->
                    <div class="flex items-center gap-4 group">
                        <div class="p-2.5 bg-[#F1F5F9] rounded-xl text-[#64748B] group-hover:bg-[#E9F2FB] group-hover:text-[#1E7CCF] transition-colors">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-[#94A3B8] uppercase tracking-[0.15em] mb-0.5">{{ __('Customer') }}</p>
                            <p class="text-sm font-bold text-[#334155]">{{ $ticket->user->name }}</p>
                        </div>
                    </div>

                    <!-- Email Info -->
                    <div class="flex items-center gap-4 group">
                        <div class="p-2.5 bg-[#F1F5F9] rounded-xl text-[#64748B] group-hover:bg-[#E9F2FB] group-hover:text-[#1E7CCF] transition-colors">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[9px] font-black text-[#94A3B8] uppercase tracking-[0.15em] mb-0.5">{{ __('Email') }}</p>
                            <p class="text-sm font-bold text-[#334155] truncate">{{ $ticket->user->email }}</p>
                        </div>
                    </div>

                    <!-- Department Info -->
                    <div class="flex items-center gap-4 group">
                        <div class="p-2.5 bg-[#F1F5F9] rounded-xl text-[#64748B] group-hover:bg-[#E9F2FB] group-hover:text-[#1E7CCF] transition-colors">
                            <i data-lucide="tag" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-[#94A3B8] uppercase tracking-[0.15em] mb-0.5">{{ __('Department') }}</p>
                            <p class="text-sm font-bold text-[#334155]">{{ $ticket->category }}</p>
                        </div>
                    </div>
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
