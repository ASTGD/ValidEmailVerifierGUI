<?php

use App\Models\VerificationOrder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        VerificationOrder::query()
            ->whereNull('order_number')
            ->orderBy('created_at')
            ->chunk(100, function ($orders): void {
                foreach ($orders as $order) {
                    $order->order_number = VerificationOrder::generateOrderNumber();
                    $order->save();
                }
            });
    }

    public function down(): void
    {
        VerificationOrder::query()->update([
            'order_number' => null,
        ]);
    }
};
