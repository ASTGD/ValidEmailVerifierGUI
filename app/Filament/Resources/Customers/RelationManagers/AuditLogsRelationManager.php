<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Log';

    protected static string|\BackedEnum|null $icon = 'heroicon-m-clipboard-document-list';

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        $user = $ownerRecord;
        return \App\Models\AdminAuditLog::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->where('subject_type', \App\Models\User::class)
                            ->where('subject_id', $user->id);
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('subject_type', \App\Models\VerificationOrder::class)
                            ->whereIn('subject_id', $user->verificationOrders()->pluck('id'));
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('subject_type', \App\Models\SupportTicket::class)
                            ->whereIn('subject_id', $user->supportTickets()->pluck('id'));
                    });
            })->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Log Entry')
                    ->searchable()
                    ->formatStateUsing(fn(string $state, $record) => match ($state) {
                        'login' => 'Customer Logged In',
                        'logout' => 'Customer Logged Out',
                        'order_placed' => 'New Order Placed',
                        'order_created' => 'Order Created by Admin',
                        'ticket_opened' => 'New Support Ticket Opened',
                        'ticket_reply_sent' => 'Support Ticket Reply Sent',
                        'ticket_resolved' => 'Support Ticket Resolved',
                        'ticket_closed' => 'Support Ticket Closed',
                        'order_activated' => 'Order Activated',
                        'order_cancelled' => 'Order Cancelled',
                        'order_requeued' => 'Order Requeued',
                        'order_reopened' => 'Order Reopened',
                        'order_marked_fraud' => 'Order Marked Fraud',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->description(function ($record) {
                        if ($record->subject_type === \App\Models\VerificationOrder::class) {
                            return "Order ID: {$record->subject_id}";
                        }
                        if ($record->subject_type === \App\Models\SupportTicket::class) {
                            return "Ticket ID: {$record->subject_id}";
                        }
                        return null;
                    }),
                TextColumn::make('user.name')
                    ->label('User')
                    ->default('System/Automated'),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->placeholder('-'),
            ])
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                $user = $this->getOwnerRecord();

                // We want to show logs where:
                // 1. The user performed the action (user_id = $user->id)
                // 2. The action was performed ON the user (subject = User:$user->id)
                // 3. The action was performed ON the user's order
                // 4. The action was performed ON the user's ticket
    
                return $query->orWhere(function ($q) use ($user) {
                    $q->where('subject_type', \App\Models\User::class)
                        ->where('subject_id', $user->id);
                })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('subject_type', \App\Models\VerificationOrder::class)
                            ->whereIn('subject_id', $user->verificationOrders()->pluck('id'));
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('subject_type', \App\Models\SupportTicket::class)
                            ->whereIn('subject_id', $user->supportTickets()->pluck('id'));
                    });
            })
            ->actions([
                \Filament\Actions\Action::make('view_related')
                    ->label('View Record')
                    ->icon('heroicon-m-link')
                    ->hidden(fn($record) => !in_array($record->subject_type, [\App\Models\VerificationOrder::class, \App\Models\SupportTicket::class]))
                    ->url(function ($record) {
                        if ($record->subject_type === \App\Models\VerificationOrder::class) {
                            return \App\Filament\Resources\VerificationOrders\VerificationOrderResource::getUrl('view', ['record' => $record->subject_id]);
                        }
                        if ($record->subject_type === \App\Models\SupportTicket::class) {
                            return \App\Filament\Resources\SupportTickets\SupportTicketResource::getUrl('view', ['record' => $record->subject_id]);
                        }
                        return null;
                    }),
            ])
            ->bulkActions([]);
    }
}
