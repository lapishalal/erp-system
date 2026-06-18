<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'tenant_id'];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($query) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $query->where('roles.tenant_id', auth()->user()->tenant_id);
            }
        });

        static::creating(function ($role) {
            if (auth()->check() && empty($role->tenant_id)) {
                $role->tenant_id = auth()->user()->tenant_id;
            }
        });
    }
}