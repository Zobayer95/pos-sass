<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;

class ReportService
{
    public function getDailySalesSummary(string $date): array
    {
        $tenantId = app('current_tenant_id');

        $summary = Order::where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->where('status', '!=', OrderStatus::CANCELLED)
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(total_amount) as total_sales,
                AVG(total_amount) as average_order_value
            ')
            ->first();

        $ordersByStatus = Order::where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->map(fn ($item) => [
                'count' => $item->count,
                'total' => (float) $item->total,
            ]);

        $topProducts = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.tenant_id', $tenantId)
            ->whereDate('orders.created_at', $date)
            ->where('orders.status', '!=', OrderStatus::CANCELLED)
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.total_price) as total_revenue
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        return [
            'date' => $date,
            'summary' => [
                'total_orders' => (int) $summary->total_orders,
                'total_sales' => (float) ($summary->total_sales ?? 0),
                'average_order_value' => round((float) ($summary->average_order_value ?? 0), 2),
            ],
            'orders_by_status' => $ordersByStatus,
            'top_products' => $topProducts->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'total_quantity' => (int) $p->total_quantity,
                'total_revenue' => (float) $p->total_revenue,
            ]),
        ];
    }

    public function getTopSellingProducts(string $startDate, string $endDate, int $limit = 5): Collection
    {
        $tenantId = app('current_tenant_id');

        return OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.tenant_id', $tenantId)
            ->whereDate('orders.created_at', '>=', $startDate)
            ->whereDate('orders.created_at', '<=', $endDate)
            ->where('orders.status', '!=', OrderStatus::CANCELLED)
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                products.price as current_price,
                SUM(order_items.quantity) as total_quantity_sold,
                SUM(order_items.total_price) as total_revenue,
                COUNT(DISTINCT orders.id) as order_count
            ')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.price')
            ->orderByDesc('total_quantity_sold')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'current_price' => (float) $p->current_price,
                'total_quantity_sold' => (int) $p->total_quantity_sold,
                'total_revenue' => (float) $p->total_revenue,
                'order_count' => (int) $p->order_count,
            ]);
    }

    public function getLowStockReport(): Collection
    {
        $tenantId = app('current_tenant_id');

        return Product::where('tenant_id', $tenantId)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity', 'asc')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'current_stock' => $p->stock_quantity,
                'low_stock_threshold' => $p->low_stock_threshold,
                'stock_deficit' => $p->low_stock_threshold - $p->stock_quantity,
            ]);
    }
}
