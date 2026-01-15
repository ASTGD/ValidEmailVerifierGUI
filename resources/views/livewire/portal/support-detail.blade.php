<div class="space-y-8">
    <!-- 1. HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('portal.support') }}" class="w-10 h-10 bg-white border border-[#E2E8F0] rounded-xl flex items-center justify-center text-[#64748B] hover:text-[#1E7CCF] transition-all shadow-sm" wire:navigate>
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-[#0F172A] tracking-tight">{{ $ticket->subject }}</h1>
                <p class="text-xs font-mono text-[#94A3B8]">Ticket #{{ $ticket->ticket_number }} • {{ $ticket->category }}</p>
            </div>
        </div>
        <span class="inline-flex items-center px-4 py-2 rounded-full text-[10px] font-black uppercase bg-white border border-[#E2E8F0] text-[#334155]">
            {{ $ticket->status->label() }}
        </span>
    </div>

    <!-- 2. CHAT THREAD -->
    <div class="bg-white rounded-[2.5rem] border border-[#E2E8F0] shadow-sm p-8 flex flex-col space-y-8">
        @foreach($messages as $msg)
            <div class="flex {{ $msg->is_admin ? 'justify-start' : 'justify-end' }}">
                <div class="max-w-[80%] space-y-2">
                    <div class="p-6 rounded-3xl {{ $msg->is_admin ? 'bg-[#F1F5F9] text-[#334155] rounded-tl-none' : 'bg-[#E9F2FB] text-[#0F172A] border border-[#1E7CCF]/10 rounded-tr-none' }}">
                        <p class="text-sm font-medium leading-relaxed">{{ $msg->content }}</p>
                        @if($msg->attachment)
                            <div class="mt-4">
                                <a href="{{ Storage::url($msg->attachment) }}" target="_blank" class="inline-flex items-center gap-2 p-2 bg-white/50 rounded-xl border border-black/5 text-xs font-bold hover:bg-white transition-all">
                                    <i data-lucide="image" class="w-4 h-4 text-[#1E7CCF]"></i> {{ __('View Attachment') }}
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 px-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-[#94A3B8]">
                            {{ $msg->is_admin ? 'Support Team' : 'You' }} • {{ $msg->created_at->diffForHumans() }}
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- 3. REPLY BOX -->
    @if($ticket->status !== \App\Enums\SupportTicketStatus::Closed)
    <div class="bg-[#F8FAFC] p-8 rounded-[2.5rem] border border-[#E2E8F0]">
        <form wire:submit.prevent="sendMessage" class="space-y-6">
            <textarea wire:model="message" rows="4" class="w-full rounded-2xl border-[#E2E8F0] focus:ring-[#1E7CCF] focus:border-[#1E7CCF] text-sm font-medium" placeholder="Type your message here..."></textarea>

            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <input type="file" wire:model="attachment" class="text-xs">
                <button type="submit" class="bg-[#1E7CCF] text-white px-10 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all hover:bg-[#1866AD]">
                    {{ __('Send Reply') }}
                </button>
            </div>
        </form>
    </div>
    @endif
</div>

<script>
    if(window.lucide) { lucide.createIcons(); }
</script>
