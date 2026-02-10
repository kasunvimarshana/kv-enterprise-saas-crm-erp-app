<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class TenantAwareModel extends Model
{
    use SoftDeletes;
    
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
        
        static::creating(function ($model) {
            if (!isset($model->tenant_id) && TenantScope::getCurrentTenantId()) {
                $model->tenant_id = TenantScope::getCurrentTenantId();
            }
        });
    }
}
