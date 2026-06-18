<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Auditable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens, Auditable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'tenant_id',
        'is_active',
        'last_login_at',
        'telegram_chat_id',
        'telegram_notifications',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
    
    public function telegramLinkCodes(): HasMany
    {
        return $this->hasMany(TelegramLinkCode::class);
    }
    
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isTenantAdmin(): bool
    {
        return $this->hasRole('Admin');
    }
}