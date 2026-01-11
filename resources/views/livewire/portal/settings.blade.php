<div class="space-y-10">
    <!-- HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">{{ __('Account Settings') }}</h1>
            <p class="text-[#64748B] font-medium mt-1">
                {{ __('Manage your profile security and notification preferences.') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Profile Settings Card -->
        <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm flex flex-col justify-between">
            <div class="flex items-start gap-6">
                <div class="w-16 h-16 bg-[#F1F5F9] text-[#1E7CCF] rounded-2xl flex items-center justify-center shrink-0">
                    <i data-lucide="user" class="w-8 h-8"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-[#0F172A]">{{ __('Personal Profile') }}</h3>
                    <p class="text-sm text-[#64748B] mt-2 leading-relaxed">
                        {{ __('Update your name, email address, and password to keep your account secure.') }}
                    </p>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-[#F8FAFC]">
                <a href="{{ route('profile') }}"
                    class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-8 py-3.5 rounded-xl font-bold text-sm shadow-lg shadow-blue-100 transition-all inline-flex items-center gap-2"
                    wire:navigate>
                    {{ __('Edit Profile') }} <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>

        <!-- Notifications Card -->
        <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm">
            <div class="flex items-start gap-6">
                <div
                    class="w-16 h-16 bg-[#FEF3C7] text-[#F59E0B] rounded-2xl flex items-center justify-center shrink-0">
                    <i data-lucide="bell" class="w-8 h-8"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-[#0F172A]">{{ __('Email Notifications') }}</h3>
                    <p class="text-sm text-[#64748B] mt-2 leading-relaxed italic">
                        {{ __('Configure when you want to receive alerts for completed verification jobs.') }}
                    </p>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-[#F8FAFC]">
                <span class="inline-flex items-center gap-2 text-xs font-bold text-[#94A3B8] uppercase tracking-widest">
                    <i data-lucide="lock" class="w-3.5 h-3.5"></i> {{ __('Coming Soon') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Danger Zone (Extra Polish) -->
    <div class="bg-white p-8 rounded-[2.5rem] border-2 border-dashed border-[#FEE2E2]">
        <div class="flex items-center gap-4">
            <i data-lucide="shield-alert" class="text-[#DC2626] w-6 h-6"></i>
            <div>
                <h4 class="text-sm font-bold text-[#DC2626]">{{ __('Security') }}</h4>
                <p class="text-xs text-[#64748B]">
                    {{ __('All your data is encrypted. To deactivate your account, please contact support.') }}</p>
            </div>
        </div>
    </div>
</div>


<script>
    lucide.createIcons();
    // Refresh for Livewire navigation
    document.addEventListener('livewire:load', () => lucide.createIcons());
    document.addEventListener('livewire:update', () => lucide.createIcons());
</script>
