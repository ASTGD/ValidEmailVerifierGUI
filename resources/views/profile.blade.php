@extends('layouts.portal')

@section('content')
<div class="space-y-10">
    <!-- HEADER SECTION -->
    <div>
        <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">{{ __('Account Settings') }}</h1>
        <p class="text-[#64748B] font-medium mt-1">{{ __('Manage your personal information, security, and account status.') }}</p>
    </div>

    <!-- 1. Profile Information Section -->
    <div class="max-w-4xl">
        <livewire:profile.update-profile-information-form />
    </div>

    <!-- 2. Update Password Section -->
    <div class="max-w-4xl">
        <livewire:profile.update-password-form />
    </div>

    <!-- 3. Delete Account Section (Danger Zone) -->
    <div class="max-w-4xl">
        <livewire:profile.delete-user-form />
    </div>
</div>
@endsection

