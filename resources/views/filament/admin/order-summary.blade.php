@php
    $orderNumber = $record->order_number ?: ('#' . $record->id);
    $orderId = $record->id ? ('ID: ' . $record->id) : null;
    $createdAt = $record->created_at?->format('M d, Y H:i') ?? '-';
    $paymentMethod = $record->paymentMethodLabel();
    $amount = sprintf('%s %.2f', strtoupper((string) ($record->currency ?: 'usd')), ((int) ($record->amount_cents ?? 0)) / 100);
    $clientName = $record->user?->name ?: ($record->user?->email ?: '-');
    $clientEmail = $record->user?->email;
    $clientLink = $record->user ? \App\Filament\Resources\Customers\CustomerResource::getUrl('view', ['record' => $record->user]) : null;
    $orderStatus = $record->status instanceof \App\Enums\VerificationOrderStatus
        ? $record->status->label()
        : ucfirst((string) $record->status);
    $orderStatusColor = match ($record->status instanceof \App\Enums\VerificationOrderStatus ? $record->status->value : (string) $record->status) {
        'pending' => 'warning',
        'processing' => 'info',
        'delivered' => 'success',
        'failed' => 'danger',
        'cancelled' => 'gray',
        'fraud' => 'danger',
        default => 'gray',
    };
    $paymentStatus = $record->paymentStatusLabel();
    $paymentStatusColor = match ($record->paymentStatusKey()) {
        'paid' => 'success',
        'failed' => 'danger',
        'refunded' => 'warning',
        default => 'gray',
    };
@endphp

<div class="admin-order-summary">
    <div class="admin-order-summary-grid">
        <div class="admin-order-summary-col">
            <div class="admin-order-summary-row">
                <span class="admin-order-summary-label">Date</span>
                <span class="admin-order-summary-value">{{ $createdAt }}</span>
            </div>
            <div class="admin-order-summary-row">
                <span class="admin-order-summary-label">Order #</span>
                <span class="admin-order-summary-value">
                    {{ $orderNumber }}@if ($orderId) <span class="admin-order-summary-muted">({{ $orderId }})</span>@endif
                </span>
            </div>
            <div class="admin-order-summary-row">
                <span class="admin-order-summary-label">Client</span>
                <span class="admin-order-summary-value">
                    @if ($clientLink)
                        <a class="admin-order-summary-link" href="{{ $clientLink }}">{{ $clientName }}</a>
                    @else
                        {{ $clientName }}
                    @endif
                    @if ($clientEmail)
                        <span class="admin-order-summary-sub">{{ $clientEmail }}</span>
                    @endif
                </span>
            </div>
            <div class="admin-order-summary-row">
                <span class="admin-order-summary-label">Order Placed By</span>
                <span class="admin-order-summary-value">
                    {{ $clientName }}
                    @if ($clientEmail)
                        <span class="admin-order-summary-sub">{{ $clientEmail }}</span>
                    @endif
                </span>
            </div>
        </div>
        <div class="admin-order-summary-col">
            <div class="admin-order-summary-row">
                <span class="admin-order-summary-label">Payment Method</span>
                <span class="admin-order-summary-value">{{ $paymentMethod ?: '-' }}</span>
            </div>
            <div class="admin-order-summary-row">
                <span class="admin-order-summary-label">Amount</span>
                <span class="admin-order-summary-value admin-order-summary-amount">{{ $amount }}</span>
            </div>
            <div class="admin-order-summary-row">
                <span class="admin-order-summary-label">Payment Status</span>
                <span class="admin-order-summary-value">
                    <x-filament::badge :color="$paymentStatusColor">{{ $paymentStatus }}</x-filament::badge>
                </span>
            </div>
            <div class="admin-order-summary-row">
                <span class="admin-order-summary-label">Order Status</span>
                <span class="admin-order-summary-value">
                    <x-filament::badge :color="$orderStatusColor">{{ $orderStatus }}</x-filament::badge>
                </span>
            </div>
        </div>
    </div>
</div>
