<?php

namespace App\Providers;

use App\Models\VerificationJob;
use App\Policies\VerificationJobPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(VerificationJob::class, VerificationJobPolicy::class);

        if (app()->environment(['local', 'testing']) && ! app()->runningInConsole()) {
            $hotFile = public_path('hot');

            if (File::exists($hotFile)) {
                $hotUrl = trim(File::get($hotFile));
                $host = $hotUrl !== '' ? parse_url($hotUrl, PHP_URL_HOST) : null;
                $port = $hotUrl !== '' ? parse_url($hotUrl, PHP_URL_PORT) : null;

                if (! $host || ! $port) {
                    File::delete($hotFile);
                    return;
                }

                $requestHost = request()?->getHost();
                $scheme = parse_url($hotUrl, PHP_URL_SCHEME) ?: 'http';
                $path = parse_url($hotUrl, PHP_URL_PATH) ?: '';
                $rewriteHosts = ['0.0.0.0', '::', '[::]', 'localhost', '127.0.0.1'];

                if ($requestHost && $requestHost !== $host && in_array($host, $rewriteHosts, true)) {
                    $hotUrl = sprintf('%s://%s:%s%s', $scheme, $requestHost, $port, $path);
                    File::put($hotFile, $hotUrl);
                    $host = $requestHost;
                }

                $connection = @fsockopen($host, $port, $errno, $errstr, 0.2);

                if (! is_resource($connection)) {
                    File::delete($hotFile);
                    return;
                }

                fclose($connection);

                $clientUrl = rtrim($hotUrl, '/').'/@vite/client';
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 0.4,
                    ],
                ]);
                $clientResponse = @file_get_contents($clientUrl, false, $context);

                if ($clientResponse === false || ! str_contains($clientResponse, 'import.meta.hot')) {
                    File::delete($hotFile);
                }
            }
        }

        RateLimiter::for('verifier-api', function (Request $request): Limit {
            $limit = (int) config('verifier.api_rate_limit_per_minute', 120);
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute($limit)->by('verifier-api|'.$key);
        });
    }
}
