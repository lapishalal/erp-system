<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = ['name', 'guard_name', 'tenant_id', 'group'];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($query) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $query->where('permissions.tenant_id', auth()->user()->tenant_id);
            }
        });

        static::creating(function ($permission) {
            if (auth()->check() && empty($permission->tenant_id)) {
                $permission->tenant_id = auth()->user()->tenant_id;
            }
        });
    }
}