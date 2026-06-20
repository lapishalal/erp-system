<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class CompanySetting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_name',
        'address',
        'phone',
        'email',
        'logo',
        'signature_name',
        'signature_image',
        'stamp_image',
    ];
}