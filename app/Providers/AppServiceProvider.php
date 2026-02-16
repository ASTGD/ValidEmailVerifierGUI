<?php

namespace App\Providers;

use App\Contracts\CacheWriteBackService;
use App\Contracts\EmailVerificationCacheStore;
use App\Contracts\EngineStorageUrlSigner;
use App\Contracts\SeedSendProvider;
use App\Models\VerificationJob;
use App\Policies\VerificationJobPolicy;
use App\Services\EmailVerificationCache\DatabaseEmailVerificationCacheStore;
use App\Services\EmailVerificationCache\DynamoDbCacheWriteBackService;
use App\Services\EmailVerificationCache\DynamoDbEmailVerificationCacheStore;
use App\Services\EmailVerificationCache\NullCacheStore;
use App\Services\SeedSend\Providers\SeedSendProviderManager;
use App\Support\EngineSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
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

        $this->app->bind(EmailVerificationCacheStore::class, function ($app) {
            $driver = config('engine.cache_store_driver');

            $storeClass = match ($driver) {
                'database' => DatabaseEmailVerificationCacheStore::class,
                'dynamodb' => DynamoDbEmailVerificationCacheStore::class,
                'null' => NullCacheStore::class,
                null => config('verifier.cache_store', NullCacheStore::class),
                default => (string) $driver,
            };

            return $app->make($storeClass);
        });

        $this->app->bind(CacheWriteBackService::class, function ($app) {
            return $app->make(DynamoDbCacheWriteBackService::class);
        });

        $this->app->bind(EngineStorageUrlSigner::class, function ($app) {
            return $app->make(\App\Services\EngineStorage\StorageEngineUrlSigner::class);
        });

        $this->app->bind(SeedSendProvider::class, function ($app) {
            /** @var SeedSendProviderManager $manager */
            $manager = $app->make(SeedSendProviderManager::class);

            return $manager->provider($manager->defaultProvider());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyRuntimeQueueSettings();

        Gate::policy(VerificationJob::class, VerificationJobPolicy::class);

        \App\Models\SupportTicket::observe(\App\Observers\SupportTicketObserver::class);
        \App\Models\SupportMessage::observe(\App\Observers\SupportMessageObserver::class);

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

        RateLimiter::for('feedback-api', function (Request $request): Limit {
            $limit = (int) config('engine.feedback_rate_limit_per_minute', 30);
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute($limit)->by('feedback-api|'.$key);
        });

        RateLimiter::for('seed-send-webhooks', function (Request $request): Limit {
            $limit = max(1, (int) config('seed_send.webhooks.rate_limit_per_minute', 120));
            $provider = strtolower(trim((string) $request->route('provider', 'unknown')));
            $clientIp = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($limit)->by(sprintf('seed-send-webhooks|%s|%s', $provider, $clientIp));
        });

        RateLimiter::for('go-internal-api', function (Request $request): Limit {
            $limit = max(1, (int) config('services.go_control_plane.internal_api_rate_limit_per_minute', 240));
            $clientIp = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($limit)->by('go-internal-api|'.$clientIp);
        });
    }

    private function applyRuntimeQueueSettings(): void
    {
        try {
            $queueConnection = EngineSettings::queueConnection();
            $cacheStore = EngineSettings::cacheStore();
        } catch (\Throwable $exception) {
            return;
        }

        $redisAvailable = $this->redisAvailable();
        $isProduction = app()->environment('production');

        if ($queueConnection === 'redis' && ! $redisAvailable) {
            if (! $isProduction) {
                $queueConnection = config('queue.default', 'database');
                if ($queueConnection === 'redis') {
                    $queueConnection = 'database';
                }
            } else {
                Log::critical('Redis unavailable while QUEUE_CONNECTION=redis in production; fallback disabled.', [
                    'context' => 'runtime_queue_settings',
                ]);
            }
        }

        if ($cacheStore === 'redis' && ! $redisAvailable) {
            if (! $isProduction) {
                $cacheStore = config('cache.default', 'database');
                if ($cacheStore === 'redis') {
                    $cacheStore = 'database';
                }
            } else {
                Log::critical('Redis unavailable while CACHE_STORE=redis in production; fallback disabled.', [
                    'context' => 'runtime_queue_settings',
                ]);
            }
        }

        config([
            'queue.default' => $queueConnection,
            'cache.default' => $cacheStore,
        ]);
    }

    private function redisAvailable(): bool
    {
        try {
            Redis::connection('default')->ping();

            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
