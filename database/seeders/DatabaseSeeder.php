<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'Demo Business',
            'slug' => 'demo-business',
            'is_active' => true,
        ]);

        $owner = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'John Owner',
            'email' => 'owner@demo.com',
            'password' => Hash::make('password'),
            'role' => UserRole::OWNER,
        ]);

        $staff = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Jane Staff',
            'email' => 'staff@demo.com',
            'password' => Hash::make('password'),
            'role' => UserRole::STAFF,
        ]);

        app()->instance('current_tenant_id', $tenant->id);

        $products = [
            ['name' => 'Laptop Pro 15', 'sku' => 'LAP-001', 'price' => 1299.99, 'stock_quantity' => 50, 'low_stock_threshold' => 10],
            ['name' => 'Wireless Mouse', 'sku' => 'MOU-001', 'price' => 29.99, 'stock_quantity' => 200, 'low_stock_threshold' => 20],
            ['name' => 'USB-C Hub', 'sku' => 'HUB-001', 'price' => 79.99, 'stock_quantity' => 75, 'low_stock_threshold' => 15],
            ['name' => 'Mechanical Keyboard', 'sku' => 'KEY-001', 'price' => 149.99, 'stock_quantity' => 8, 'low_stock_threshold' => 10],
            ['name' => '27" Monitor', 'sku' => 'MON-001', 'price' => 399.99, 'stock_quantity' => 30, 'low_stock_threshold' => 5],
            ['name' => 'Webcam HD', 'sku' => 'CAM-001', 'price' => 89.99, 'stock_quantity' => 3, 'low_stock_threshold' => 10],
            ['name' => 'Headphones', 'sku' => 'HPH-001', 'price' => 199.99, 'stock_quantity' => 45, 'low_stock_threshold' => 10],
            ['name' => 'Desk Lamp', 'sku' => 'LMP-001', 'price' => 49.99, 'stock_quantity' => 100, 'low_stock_threshold' => 15],
        ];

        foreach ($products as $productData) {
            Product::create(array_merge($productData, ['tenant_id' => $tenant->id]));
        }

        $customers = [
            ['name' => 'Alice Johnson', 'email' => 'alice@example.com', 'phone' => '+1-555-0101', 'address' => '123 Main St, City, ST 12345'],
            ['name' => 'Bob Smith', 'email' => 'bob@example.com', 'phone' => '+1-555-0102', 'address' => '456 Oak Ave, Town, ST 67890'],
            ['name' => 'Carol White', 'email' => 'carol@example.com', 'phone' => '+1-555-0103', 'address' => '789 Pine Rd, Village, ST 11111'],
        ];

        foreach ($customers as $customerData) {
            Customer::create(array_merge($customerData, ['tenant_id' => $tenant->id]));
        }

        $this->command->info('Seeded Demo Business tenant:');
        $this->command->info('  Tenant ID: '.$tenant->id);
        $this->command->info('  Owner: owner@demo.com / password');
        $this->command->info('  Staff: staff@demo.com / password');
        $this->command->info('  Products: '.Product::count());
        $this->command->info('  Customers: '.Customer::count());
    }
}
