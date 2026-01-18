<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerificationOutcomeIngestion extends Model
{
    public const TYPE_API = 'api';
    public const TYPE_IMPORT = 'import';

    protected $fillable = [
        'type',
        'source',
        'item_count',
        'imported_count',
        'skipped_count',
        'error_count',
        'user_id',
        'token_name',
        'ip_address',
        'import_id',
        'error_message',
    ];

    protected $casts = [
        'item_count' => 'integer',
        'imported_count' => 'integer',
        'skipped_count' => 'integer',
        'error_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(EmailVerificationOutcomeImport::class, 'import_id');
    }
}
