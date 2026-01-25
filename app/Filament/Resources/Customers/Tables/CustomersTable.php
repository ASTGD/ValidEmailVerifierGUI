<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company_name')
                    ->label('Company Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('verification_jobs_count')
                    ->label('Services')
                    ->counts('verificationJobs')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'closed' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                Filter::make('client_attributes')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('client_name_filter')->label('Client/Company Name'),
                        \Filament\Forms\Components\TextInput::make('email_filter')->label('Email Address'),
                        \Filament\Forms\Components\TextInput::make('phone_filter')->label('Phone Number'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['client_name_filter'],
                                fn(Builder $query, $date) => $query->where(fn($q) => $q->where('first_name', 'like', "%{$date}%")
                                    ->orWhere('last_name', 'like', "%{$date}%")
                                    ->orWhere('company_name', 'like', "%{$date}%"))
                            )
                            ->when(
                                $data['email_filter'],
                                fn(Builder $query, $date) => $query->where('email', 'like', "%{$date}%")
                            )
                            ->when(
                                $data['phone_filter'],
                                fn(Builder $query, $date) => $query->where('phone', 'like', "%{$date}%")
                            );
                    }),
                SelectFilter::make('client_group')
                    ->label('Client Group')
                    ->options([
                        'none' => 'None',
                        'vip' => 'VIP',
                        'reseller' => 'Reseller',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'closed' => 'Closed',
                    ]),
            ])
            ->emptyStateHeading('No customers found')
            ->emptyStateDescription('Try adjusting your search or filters.');
    }
}

