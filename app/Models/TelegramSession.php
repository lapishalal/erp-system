<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\BelongsToTenant;
class TelegramSession extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['chat_id', 'user_id', 'state', 'data', 'last_activity'];
    protected $casts = [
        'tenant_id','data' => 'array', 'last_activity' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}