<?php

namespace App\Providers;

use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Persistence\Repositories\OrganizationRepository;
use App\Infrastructure\Persistence\Repositories\TenantRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tenant Module
        $this->app->bind(
            TenantRepositoryInterface::class,
            TenantRepository::class
        );
        
        // Organization Module
        $this->app->bind(
            OrganizationRepositoryInterface::class,
            OrganizationRepository::class
        );
    }
    
    public function boot(): void
    {
        //
    }
}
