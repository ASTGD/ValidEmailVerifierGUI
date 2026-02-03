<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();

            $table->string('language')->default('en');
            $table->string('status')->default('active'); // active, inactive, closed
            $table->string('payment_method')->nullable();
            $table->string('billing_contact')->default('default');
            $table->string('currency')->default('USD');
            $table->string('client_group')->nullable();

            // Notification flags
            $table->boolean('notify_general')->default(true);
            $table->boolean('notify_invoice')->default(true);
            $table->boolean('notify_support')->default(true);
            $table->boolean('notify_product')->default(true);
            $table->boolean('notify_domain')->default(true);
            $table->boolean('notify_affiliate')->default(true);

            // Settings
            $table->boolean('allow_late_fees')->default(true);
            $table->boolean('send_overdue_notices')->default(true);
            $table->boolean('tax_exempt')->default(false);
            $table->boolean('separate_invoices')->default(false);
            $table->boolean('disable_cc_processing')->default(false);
            $table->boolean('marketing_emails_opt_in')->default(false);
            $table->boolean('status_update_enabled')->default(true);
            $table->boolean('allow_sso')->default(true);

            $table->text('admin_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
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
                'admin_notes'
            ]);
        });
    }
};
