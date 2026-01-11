<?php

namespace App\Filament\Resources\PricingPlans\Tables;

use App\Models\PricingPlan;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PricingPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('billing_interval')
                    ->label('Interval')
                    ->formatStateUsing(fn ($state): string => $state ? ucfirst((string) $state) : '-'),
                TextColumn::make('price_per_email')
                    ->label('Per email')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 4) : '-'),
                TextColumn::make('price_per_1000')
                    ->label('Per 1,000')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2) : '-'),
                TextColumn::make('min_emails')
                    ->label('Min emails')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('max_emails')
                    ->label('Max emails')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('credits_per_month')
                    ->label('Credits')
                    ->numeric()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
            ])
            ->filters([
                SelectFilter::make('billing_interval')
                    ->label('Interval')
                    ->options([
                        'month' => 'Monthly',
                        'year' => 'Yearly',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->emptyStateHeading('No pricing plans yet')
            ->emptyStateDescription('Create your first pricing plan to configure billing.')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
