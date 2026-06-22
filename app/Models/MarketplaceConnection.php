<?php

namespace App\Models;

use App\Enums\MarketplacePlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceConnection extends Model
{
    protected $fillable = [
        'tenant_id',
        'platform',
        'shop_id',
        'shop_name',
        'app_key',
        'app_secret',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'webhook_url',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'platform' => MarketplacePlatform::class,
        'is_active' => 'boolean',
        'settings' => 'array',
        'token_expires_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class, 'connection_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MarketplaceLog::class, 'connection_id');
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }
        return $this->token_expires_at->isPast();
    }
}