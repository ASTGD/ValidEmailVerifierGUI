<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Roles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'enhanced_enabled',
        'first_name',
        'last_name',
        'company_name',
        'address_1',
        'address_2',
        'city',
        'state',
        'postcode',
        'country',
        'phone',
        'language',
        'status',
        'payment_method',
        'billing_contact',
        'currency',
        'client_group',
        'notify_general',
        'notify_invoice',
        'notify_support',
        'notify_product',
        'notify_domain',
        'notify_affiliate',
        'allow_late_fees',
        'send_overdue_notices',
        'tax_exempt',
        'separate_invoices',
        'disable_cc_processing',
        'marketing_emails_opt_in',
        'status_update_enabled',
        'allow_sso',
        'admin_notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'enhanced_enabled' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        return $this->hasRole(Roles::ADMIN);
    }

    public function verificationJobs(): HasMany
    {
        return $this->hasMany(VerificationJob::class);
    }

    public function verificationOrders(): HasMany
    {
        return $this->hasMany(VerificationOrder::class);
    }

    public function checkoutIntents(): HasMany
    {
        return $this->hasMany(CheckoutIntent::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class);
    }

    public function seedSendConsents(): HasMany
    {
        return $this->hasMany(SeedSendConsent::class);
    }

    public function seedSendCampaigns(): HasMany
    {
        return $this->hasMany(SeedSendCampaign::class);
    }
}
