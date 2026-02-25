<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineWorkerPool extends Model
{
    public const PROVIDERS = ['generic', 'gmail', 'microsoft', 'yahoo'];

    public const PROFILES = ['standard', 'low_hit', 'warmup'];

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'is_default',
        'provider_profiles',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'provider_profiles' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(EngineServer::class, 'worker_pool_id');
    }

    /**
     * @return array<string, string>
     */
    public function normalizedProviderProfiles(): array
    {
        $configured = is_array($this->provider_profiles) ? $this->provider_profiles : [];
        $normalized = [];

        foreach (self::PROVIDERS as $provider) {
            $profile = strtolower(trim((string) ($configured[$provider] ?? '')));
            if (! in_array($profile, self::PROFILES, true)) {
                $profile = 'standard';
            }
            $normalized[$provider] = $profile;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    public static function defaultProviderProfiles(): array
    {
        return [
            'generic' => 'standard',
            'gmail' => 'standard',
            'microsoft' => 'standard',
            'yahoo' => 'standard',
        ];
    }

    public static function resolveDefaultId(): ?int
    {
        return self::query()
            ->where('is_default', true)
            ->value('id');
    }

    public static function resolveDefaultSlug(): string
    {
        $slug = self::query()
            ->where('is_default', true)
            ->value('slug');

        $slug = trim((string) $slug);

        return $slug !== '' ? $slug : 'default';
    }
}
