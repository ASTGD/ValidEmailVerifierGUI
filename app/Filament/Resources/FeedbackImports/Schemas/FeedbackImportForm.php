<?php

namespace App\Filament\Resources\FeedbackImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FeedbackImportForm
{
    public static function configure(Schema $schema): Schema
    {
        $disk = config('verifier.storage_disk') ?: config('filesystems.default');
        $directory = config('engine.feedback_imports_prefix');

        return $schema
            ->components([
                Section::make('Feedback Import')
                    ->schema([
                        FileUpload::make('file_key')
                            ->label('CSV file')
                            ->disk($disk)
                            ->directory($directory)
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->required(),
                        Hidden::make('file_disk')
                            ->default($disk)
                            ->dehydrated(),
                        TextInput::make('source')
                            ->label('Source')
                            ->default('admin_import')
                            ->maxLength(255)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}
