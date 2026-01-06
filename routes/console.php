<?php

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:bootstrap-users
    {--admin-email= : Admin email address}
    {--admin-password= : Admin password}
    {--verifier-email= : Verifier service email address}
    {--verifier-password= : Verifier service password}
    {--force-reset : Reset passwords for existing users}
', function () {
    $guardName = config('auth.defaults.guard');

    foreach (Roles::all() as $roleName) {
        Role::findOrCreate($roleName, $guardName);
    }

    $adminEmail = $this->option('admin-email') ?: env('ADMIN_EMAIL');
    if (!$adminEmail) {
        $adminEmail = $this->ask('Admin email');
    }

    $adminPassword = $this->option('admin-password') ?: env('ADMIN_PASSWORD');
    if (!$adminPassword) {
        $adminPassword = $this->secret('Admin password');
    }

    $admin = User::where('email', $adminEmail)->first();
    if ($admin) {
        if ($this->option('force-reset') || $this->confirm('Reset password for existing admin user?', false)) {
            $admin->forceFill([
                'password' => Hash::make($adminPassword),
            ])->save();
        }
    } else {
        $admin = User::create([
            'name' => 'Admin',
            'email' => $adminEmail,
            'password' => Hash::make($adminPassword),
        ]);
    }

    $admin->assignRole(Roles::ADMIN);

    $verifierEmail = $this->option('verifier-email') ?: env('VERIFIER_SERVICE_EMAIL');
    if (!$verifierEmail) {
        $verifierEmail = $this->ask('Verifier service email');
    }

    $verifierPassword = $this->option('verifier-password') ?: env('VERIFIER_SERVICE_PASSWORD');
    if (!$verifierPassword) {
        $verifierPassword = $this->secret('Verifier service password');
    }

    $verifier = User::where('email', $verifierEmail)->first();
    if ($verifier) {
        if ($this->option('force-reset') || $this->confirm('Reset password for existing verifier user?', false)) {
            $verifier->forceFill([
                'password' => Hash::make($verifierPassword),
            ])->save();
        }
    } else {
        $verifier = User::create([
            'name' => 'Verifier Service',
            'email' => $verifierEmail,
            'password' => Hash::make($verifierPassword),
        ]);
    }

    $verifier->assignRole(Roles::VERIFIER_SERVICE);

    $this->info('Roles ensured and users provisioned.');
})->purpose('Create roles, an admin user, and a verifier-service user');
