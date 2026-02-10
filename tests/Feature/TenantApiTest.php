<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_can_create_tenant(): void
    {
        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Test Corporation',
            'domain' => 'testcorp',
            'trial_days' => 30,
        ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'domain',
                    'status',
                ],
            ])
            ->assertJson([
                'message' => 'Tenant created successfully',
                'data' => [
                    'name' => 'Test Corporation',
                    'domain' => 'testcorp',
                    'status' => 'pending',
                ],
            ]);
        
        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Corporation',
            'domain' => 'testcorp',
            'status' => 'pending',
        ]);
    }
    
    public function test_can_list_tenants(): void
    {
        // Create test tenants
        $this->postJson('/api/v1/tenants', [
            'name' => 'Tenant 1',
            'domain' => 'tenant1',
        ]);
        
        $this->postJson('/api/v1/tenants', [
            'name' => 'Tenant 2',
            'domain' => 'tenant2',
        ]);
        
        $response = $this->getJson('/api/v1/tenants');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'domain',
                        'status',
                        'created_at',
                    ],
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }
    
    public function test_can_get_tenant_by_id(): void
    {
        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Test Tenant',
            'domain' => 'testtenant',
        ]);
        
        $tenantId = $createResponse->json('data.id');
        
        $response = $this->getJson("/api/v1/tenants/{$tenantId}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'domain',
                    'status',
                    'settings',
                    'trial_ends_at',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $tenantId,
                    'name' => 'Test Tenant',
                    'domain' => 'testtenant',
                ],
            ]);
    }
    
    public function test_can_activate_tenant(): void
    {
        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Test Tenant',
            'domain' => 'testtenant',
        ]);
        
        $tenantId = $createResponse->json('data.id');
        
        $response = $this->postJson("/api/v1/tenants/{$tenantId}/activate");
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Tenant activated successfully',
                'data' => [
                    'id' => $tenantId,
                    'status' => 'active',
                ],
            ]);
        
        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'status' => 'active',
        ]);
    }
    
    public function test_validates_tenant_creation_data(): void
    {
        $response = $this->postJson('/api/v1/tenants', [
            // Missing required fields
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'domain']);
    }
    
    public function test_domain_must_be_unique(): void
    {
        $this->postJson('/api/v1/tenants', [
            'name' => 'Tenant 1',
            'domain' => 'duplicate',
        ]);
        
        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Tenant 2',
            'domain' => 'duplicate',
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['domain']);
    }
}

