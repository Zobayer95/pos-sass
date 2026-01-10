<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    private Customer $customer;

    private Product $product;

    private string $ownerToken;

    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->owner()->create(['tenant_id' => $this->tenant->id]);
        $this->staff = User::factory()->staff()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 100,
            'price' => 50.00,
        ]);

        $this->ownerToken = $this->owner->createToken('test-token')->plainTextToken;
        $this->staffToken = $this->staff->createToken('test-token')->plainTextToken;
    }

    public function test_can_create_order(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5],
            ],
        ], [
            'Authorization' => 'Bearer '.$this->staffToken,
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                    'total_amount' => 250.00,
                ],
            ]);

        $this->product->refresh();
        $this->assertEquals(95, $this->product->stock_quantity);
    }

    public function test_cannot_create_order_with_insufficient_stock(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 150],
            ],
        ], [
            'Authorization' => 'Bearer '.$this->staffToken,
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->product->refresh();
        $this->assertEquals(100, $this->product->stock_quantity);
    }

    public function test_owner_can_cancel_order(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::PENDING,
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 50.00,
            'total_price' => 500.00,
        ]);

        $this->product->update(['stock_quantity' => 90]);

        $response = $this->postJson("/api/orders/{$order->id}/cancel", [], [
            'Authorization' => 'Bearer '.$this->ownerToken,
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'cancelled',
                ],
            ]);

        $this->product->refresh();
        $this->assertEquals(100, $this->product->stock_quantity);
    }

    public function test_staff_cannot_cancel_order(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/cancel", [], [
            'Authorization' => 'Bearer '.$this->staffToken,
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_can_update_order_status(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'paid',
        ], [
            'Authorization' => 'Bearer '.$this->staffToken,
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'paid',
                ],
            ]);
    }
}
