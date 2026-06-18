<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // A. Global Scope: otomatis filter tenant_id saat query
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check() && Auth::user()->tenant_id) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', Auth::user()->tenant_id);
            }
        });

        // B. Auto-fill tenant_id saat create
        static::creating(function (Model $model) {
            if (Auth::check() && empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });

        // C. Prevent update tenant_id (security)
        static::updating(function (Model $model) {
            if ($model->isDirty('tenant_id') && $model->getOriginal('tenant_id') !== null) {
                throw new \RuntimeException('tenant_id cannot be changed.');
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}