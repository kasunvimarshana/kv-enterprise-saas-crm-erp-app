<?php

use App\Presentation\Api\V1\Controllers\OrganizationController;
use App\Presentation\Api\V1\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

// Public tenant management routes (no tenant context required)
Route::prefix('v1')->group(function () {
    Route::prefix('tenants')->group(function () {
        Route::get('/', [TenantController::class, 'index']);
        Route::post('/', [TenantController::class, 'store']);
        Route::get('/{id}', [TenantController::class, 'show']);
        Route::post('/{id}/activate', [TenantController::class, 'activate']);
    });
});

// Tenant-scoped routes (require tenant context)
Route::prefix('v1')->middleware('tenant')->group(function () {
    // Organizations
    Route::prefix('organizations')->group(function () {
        Route::get('/', [OrganizationController::class, 'index']);
        Route::post('/', [OrganizationController::class, 'store']);
        Route::get('/{id}', [OrganizationController::class, 'show']);
        Route::get('/{id}/children', [OrganizationController::class, 'children']);
    });
});
