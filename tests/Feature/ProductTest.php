<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->owner()->create(['tenant_id' => $this->tenant->id]);
        $this->staff = User::factory()->staff()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->owner->createToken('test-token')->plainTextToken;
    }

    public function test_can_list_products(): void
    {
        app()->instance('current_tenant_id', $this->tenant->id);
        Product::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/products', [
            'Authorization' => 'Bearer '.$this->token,
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_owner_can_create_product(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock_quantity' => 100,
            'low_stock_threshold' => 10,
        ], [
            'Authorization' => 'Bearer '.$this->token,
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test Product',
                    'sku' => 'TEST-001',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->tenant->id,
            'sku' => 'TEST-001',
        ]);
    }

    public function test_staff_cannot_create_product(): void
    {
        $staffToken = $this->staff->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock_quantity' => 100,
        ], [
            'Authorization' => 'Bearer '.$staffToken,
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_sku_must_be_unique_per_tenant(): void
    {
        app()->instance('current_tenant_id', $this->tenant->id);
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'EXISTING-SKU',
        ]);

        $response = $this->postJson('/api/products', [
            'name' => 'Another Product',
            'sku' => 'EXISTING-SKU',
            'price' => 49.99,
            'stock_quantity' => 50,
        ], [
            'Authorization' => 'Bearer '.$this->token,
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_same_sku_allowed_for_different_tenants(): void
    {
        $otherTenant = Tenant::factory()->create();
        Product::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Product',
            'sku' => 'SAME-SKU',
            'price' => 10,
            'stock_quantity' => 10,
        ]);

        $response = $this->postJson('/api/products', [
            'name' => 'My Product',
            'sku' => 'SAME-SKU',
            'price' => 99.99,
            'stock_quantity' => 100,
        ], [
            'Authorization' => 'Bearer '.$this->token,
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(201);
    }
}
