<?php

namespace App\Models;

use App\Enums\MarketplacePlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'connection_id',
        'platform',
        'event_type',
        'direction',
        'payload',
        'response',
        'http_status',
        'is_success',
        'error_message',
        'ip_address',
    ];

    protected $casts = [
        'platform' => MarketplacePlatform::class,
        'payload' => 'array',
        'response' => 'array',
        'is_success' => 'boolean',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MarketplaceConnection::class, 'connection_id');
    }
}