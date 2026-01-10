<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_cannot_see_other_tenant_products(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $user1 = User::factory()->owner()->create(['tenant_id' => $tenant1->id]);

        Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant1->id,
            'name' => 'Tenant 1 Product',
            'sku' => 'T1-001',
            'price' => 100,
            'stock_quantity' => 10,
        ]);

        Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant2->id,
            'name' => 'Tenant 2 Product',
            'sku' => 'T2-001',
            'price' => 200,
            'stock_quantity' => 20,
        ]);

        $token = $user1->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/products', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID' => $tenant1->id,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Tenant 1 Product', $data[0]['name']);
    }

    public function test_tenant_cannot_see_other_tenant_customers(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $user1 = User::factory()->owner()->create(['tenant_id' => $tenant1->id]);

        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant1->id,
            'name' => 'Customer A',
            'email' => 'a@example.com',
        ]);

        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant2->id,
            'name' => 'Customer B',
            'email' => 'b@example.com',
        ]);

        $token = $user1->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/customers', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID' => $tenant1->id,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Customer A', $data[0]['name']);
    }

    public function test_tenant_cannot_access_other_tenant_product(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $user1 = User::factory()->owner()->create(['tenant_id' => $tenant1->id]);

        $product2 = Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant2->id,
            'name' => 'Tenant 2 Product',
            'sku' => 'T2-001',
            'price' => 200,
            'stock_quantity' => 20,
        ]);

        $token = $user1->createToken('test-token')->plainTextToken;

        $response = $this->getJson("/api/products/{$product2->id}", [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID' => $tenant1->id,
        ]);

        // Returns 403 (Forbidden) or 404 (Not Found) - both are valid security responses
        // 403 is returned when authorization check fails before tenant scope filtering
        $response->assertStatus(403);
    }

    public function test_invalid_tenant_id_returns_error(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/products', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID' => 99999,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or inactive tenant',
            ]);
    }

    public function test_inactive_tenant_cannot_access_api(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => false]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/products', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID' => $tenant->id,
        ]);

        $response->assertStatus(403);
    }
}
