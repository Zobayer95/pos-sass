<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    public function dailySales(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['sometimes', 'date', 'date_format:Y-m-d'],
        ]);

        $date = $request->input('date', now()->toDateString());

        $report = $this->reportService->getDailySalesSummary($date);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $limit = $request->input('limit', 5);

        $products = $this->reportService->getTopSellingProducts($startDate, $endDate, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'products' => $products,
            ],
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $products = $this->reportService->getLowStockReport();

        return response()->json([
            'success' => true,
            'data' => [
                'total_low_stock_items' => $products->count(),
                'products' => $products,
            ],
        ]);
    }
}
