<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Organization\Entities\Organization;
use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\OrganizationModel;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function find(string $id): ?Organization
    {
        $model = OrganizationModel::find($id);
        
        return $model ? $this->toDomain($model) : null;
    }
    
    public function findByTenantAndCode(string $tenantId, string $code): ?Organization
    {
        $model = OrganizationModel::where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();
        
        return $model ? $this->toDomain($model) : null;
    }
    
    public function findChildren(string $parentId): array
    {
        return OrganizationModel::where('parent_id', $parentId)
            ->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }
    
    public function findByTenant(string $tenantId): array
    {
        return OrganizationModel::where('tenant_id', $tenantId)
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }
    
    public function save(Organization $organization): void
    {
        $model = OrganizationModel::findOrNew($organization->getId());
        
        $model->fill([
            'id' => $organization->getId(),
            'tenant_id' => $organization->getTenantId(),
            'parent_id' => $organization->getParentId(),
            'name' => $organization->getName(),
            'code' => $organization->getCode(),
            'level' => $organization->getLevel(),
            'path' => $organization->getPath(),
            'status' => $organization->getStatus()->value,
            'settings' => $organization->getSettings(),
        ]);
        
        $model->save();
        
        // Dispatch domain events
        foreach ($organization->pullDomainEvents() as $event) {
            event($event);
        }
    }
    
    public function delete(Organization $organization): void
    {
        $model = OrganizationModel::find($organization->getId());
        
        if ($model) {
            $model->delete();
        }
    }
    
    private function toDomain(OrganizationModel $model): Organization
    {
        return new Organization(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            code: $model->code,
            status: OrganizationStatus::from($model->status),
            parentId: $model->parent_id,
            level: $model->level,
            path: $model->path,
            settings: $model->settings ?? [],
            createdAt: $model->created_at,
            updatedAt: $model->updated_at
        );
    }
}
