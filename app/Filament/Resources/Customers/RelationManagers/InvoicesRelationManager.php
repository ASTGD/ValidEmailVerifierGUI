<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\Invoice;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Schemas\Schema;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices';

    protected static string|\BackedEnum|null $icon = 'heroicon-m-banknotes';

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->invoices()->count();
    }

    public function form(Schema $form): Schema
    {
        return \App\Filament\Resources\InvoiceResource\Schemas\InvoiceForm::configure($form);
    }

    public function infolist(Schema $infolist): Schema
    {
        return \App\Filament\Resources\InvoiceResource\Schemas\InvoiceInfolist::configure($infolist);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_number')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice Number')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('warning'),
                TextColumn::make('date')
                    ->label('Invoice Date')
                    ->sortable()
                    ->dateTime(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Date Paid')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(function ($state, Invoice $record): string {
                        $currency = strtoupper((string) ($record->currency ?: 'usd'));
                        $amount = ((int) $state) / 100;
                        return sprintf('%s %.2f', $currency, $amount);
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Unpaid' => 'warning',
                        'Cancelled' => 'danger',
                        'Refunded' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-m-plus')
                    ->color('primary'),
            ])
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-m-eye')
                    ->color('gray'),

                EditAction::make()
                    ->icon('heroicon-m-pencil-square')
                    ->color('info')
                    ->label('Edit'),

                DeleteAction::make()
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->label('Delete'),

                Action::make('download_manual')
                    ->label('PDF')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->action(function (Invoice $record) {
                        return response()->streamDownload(function () use ($record) {
                            echo \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', ['invoice' => $record])->output();
                        }, 'invoice-' . $record->invoice_number . '.pdf');
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
