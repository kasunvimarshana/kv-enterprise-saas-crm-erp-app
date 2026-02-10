<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Enums\TenantStatus;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\TenantModel;

class TenantRepository implements TenantRepositoryInterface
{
    public function find(string $id): ?Tenant
    {
        $model = TenantModel::find($id);
        
        return $model ? $this->toDomain($model) : null;
    }
    
    public function findByDomain(string $domain): ?Tenant
    {
        $model = TenantModel::where('domain', $domain)->first();
        
        return $model ? $this->toDomain($model) : null;
    }
    
    public function findAll(): array
    {
        return TenantModel::all()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }
    
    public function save(Tenant $tenant): void
    {
        $model = TenantModel::findOrNew($tenant->getId());
        
        $model->fill([
            'id' => $tenant->getId(),
            'name' => $tenant->getName(),
            'domain' => $tenant->getDomain(),
            'status' => $tenant->getStatus()->value,
            'database_name' => $tenant->getDatabaseName(),
            'settings' => $tenant->getSettings(),
            'trial_ends_at' => $tenant->getTrialEndsAt(),
        ]);
        
        $model->save();
        
        // Dispatch domain events
        foreach ($tenant->pullDomainEvents() as $event) {
            event($event);
        }
    }
    
    public function delete(Tenant $tenant): void
    {
        $model = TenantModel::find($tenant->getId());
        
        if ($model) {
            $model->delete();
        }
    }
    
    public function exists(string $id): bool
    {
        return TenantModel::where('id', $id)->exists();
    }
    
    private function toDomain(TenantModel $model): Tenant
    {
        return new Tenant(
            id: $model->id,
            name: $model->name,
            domain: $model->domain,
            status: TenantStatus::from($model->status),
            databaseName: $model->database_name,
            settings: $model->settings ?? [],
            trialEndsAt: $model->trial_ends_at,
            createdAt: $model->created_at,
            updatedAt: $model->updated_at
        );
    }
}
