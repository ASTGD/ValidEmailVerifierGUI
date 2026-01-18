<?php

use App\Enums\VerificationJobStatus;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\EmailVerificationOutcome;
use App\Models\EmailVerificationOutcomeImport;
use App\Services\JobStorage;
use App\Support\RetentionSettings;
use App\Support\Roles;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

Artisan::command('app:issue-verifier-token
    {--verifier-email= : Verifier service email address}
    {--name=verifier-service : Token name}
', function () {
    $verifierEmail = $this->option('verifier-email') ?: env('VERIFIER_SERVICE_EMAIL');
    if (! $verifierEmail) {
        $verifierEmail = $this->ask('Verifier service email');
    }

    $verifier = User::where('email', $verifierEmail)->first();
    if (! $verifier) {
        $this->error('Verifier service user not found. Run app:bootstrap-users first.');
        return 1;
    }

    $token = $verifier->createToken($this->option('name'))->plainTextToken;

    $this->line('Verifier token:');
    $this->line($token);

    return 0;
})->purpose('Issue a Sanctum token for the verifier-service user');

Artisan::command('app:purge-verification-jobs
    {--days= : Purge jobs completed before this many days ago}
    {--dry-run : Show what would be deleted without removing anything}
', function () {
    $daysOption = $this->option('days');
    $days = is_null($daysOption) ? RetentionSettings::days() : (int) $daysOption;
    if ($days < 0) {
        $this->error('Retention days must be zero or greater.');
        return 1;
    }

    $cutoff = now()->subDays($days);

    $jobs = VerificationJob::query()
        ->whereIn('status', [VerificationJobStatus::Completed, VerificationJobStatus::Failed])
        ->whereNotNull('finished_at')
        ->where('finished_at', '<=', $cutoff)
        ->get();

    if ($jobs->isEmpty()) {
        $this->info('No verification jobs to purge.');
        return 0;
    }

    $storage = app(JobStorage::class);
    $defaultDisk = $storage->disk();
    $dryRun = (bool) $this->option('dry-run');
    $deleted = 0;

    foreach ($jobs as $job) {
        $inputDisk = $job->input_disk ?: $defaultDisk;
        if ($job->input_key && Storage::disk($inputDisk)->exists($job->input_key)) {
            if (! $dryRun) {
                Storage::disk($inputDisk)->delete($job->input_key);
            }
        }

        $outputDisk = $job->output_disk ?: $defaultDisk;
        if ($job->output_key && Storage::disk($outputDisk)->exists($job->output_key)) {
            if (! $dryRun) {
                Storage::disk($outputDisk)->delete($job->output_key);
            }
        }

        if (! $dryRun) {
            $job->delete();
        }

        $deleted++;
    }

    $message = $dryRun
        ? sprintf('Dry run complete. %d job(s) would be purged.', $deleted)
        : sprintf('Purge complete. %d job(s) deleted.', $deleted);

    $this->info($message);

    return 0;
})->purpose('Purge completed/failed verification jobs and their stored files');

Artisan::command('prune:email-outcomes
    {--days= : Prune outcomes observed before this many days ago}
    {--dry-run : Show what would be deleted without removing anything}
', function () {
    $daysOption = $this->option('days');
    $days = is_null($daysOption) ? (int) config('engine.feedback_retention_days', 180) : (int) $daysOption;
    if ($days < 0) {
        $this->error('Retention days must be zero or greater.');
        return 1;
    }

    $cutoff = now()->subDays($days);
    $query = EmailVerificationOutcome::query()->where('observed_at', '<=', $cutoff);
    $count = $query->count();

    if ($count === 0) {
        $this->info('No email outcomes to prune.');
        return 0;
    }

    $dryRun = (bool) $this->option('dry-run');
    $deleted = $dryRun ? 0 : $query->delete();

    $message = $dryRun
        ? sprintf('Dry run complete. %d outcome(s) would be pruned.', $count)
        : sprintf('Prune complete. %d outcome(s) deleted.', $deleted);

    $this->info($message);

    return 0;
})->purpose('Prune email verification outcomes older than retention window');

Artisan::command('prune:feedback-imports
    {--days= : Prune imports created before this many days ago}
    {--dry-run : Show what would be deleted without removing anything}
', function () {
    $daysOption = $this->option('days');
    $days = is_null($daysOption) ? (int) config('engine.feedback_import_retention_days', 90) : (int) $daysOption;
    if ($days < 0) {
        $this->error('Retention days must be zero or greater.');
        return 1;
    }

    $cutoff = now()->subDays($days);
    $imports = EmailVerificationOutcomeImport::query()
        ->where('created_at', '<=', $cutoff)
        ->get();

    if ($imports->isEmpty()) {
        $this->info('No feedback imports to prune.');
        return 0;
    }

    $dryRun = (bool) $this->option('dry-run');
    $deleted = 0;

    foreach ($imports as $import) {
        if (! $dryRun && $import->file_disk && $import->file_key) {
            if (Storage::disk($import->file_disk)->exists($import->file_key)) {
                Storage::disk($import->file_disk)->delete($import->file_key);
            }
        }

        if (! $dryRun) {
            $import->delete();
        }

        $deleted++;
    }

    $message = $dryRun
        ? sprintf('Dry run complete. %d import(s) would be pruned.', $deleted)
        : sprintf('Prune complete. %d import(s) deleted.', $deleted);

    $this->info($message);

    return 0;
})->purpose('Prune feedback imports and stored files older than retention window');
