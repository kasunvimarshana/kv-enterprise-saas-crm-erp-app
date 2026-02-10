<?php

namespace App\Infrastructure\Persistence\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    private static ?string $currentTenantId = null;
    
    public static function setCurrentTenantId(?string $tenantId): void
    {
        self::$currentTenantId = $tenantId;
    }
    
    public static function getCurrentTenantId(): ?string
    {
        return self::$currentTenantId;
    }
    
    public static function clearCurrentTenantId(): void
    {
        self::$currentTenantId = null;
    }
    
    public function apply(Builder $builder, Model $model): void
    {
        if (self::$currentTenantId !== null && $model->getTable() !== 'tenants') {
            $builder->where($model->getTable() . '.tenant_id', self::$currentTenantId);
        }
    }
}
