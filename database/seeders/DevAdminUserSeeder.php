<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $email = env('DEV_ADMIN_EMAIL');
        $password = env('DEV_ADMIN_PASSWORD');
        $name = env('DEV_ADMIN_NAME');

        if (! $email || ! $password) {
            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name ?: $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->assignRole($user, Roles::ADMIN);
    }

    private function assignRole(User $user, string $role): void
    {
        if (! method_exists($user, 'assignRole')) {
            return;
        }

        $roleClass = \Spatie\Permission\Models\Role::class;

        if (! class_exists($roleClass)) {
            return;
        }

        $guard = config('auth.defaults.guard');
        $roleClass::findOrCreate($role, $guard);
        $user->assignRole($role);
    }
}
