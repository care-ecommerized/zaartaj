<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\SalesReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin landing screen: order pipeline, headline KPIs and recent orders,
 * scoped to a selectable date range (default the last 30 days).
 */
class DashboardController extends Controller
{
    public function __construct(private readonly SalesReport $report) {}

    public function index(Request $request): Response
    {
        $range = $this->report->normaliseRange((string) $request->query('range', SalesReport::DEFAULT_RANGE));

        $summary = $this->report->summary($range);

        return Inertia::render('admin/dashboard', [
            'range' => $summary['range'],
            'ranges' => SalesReport::RANGES,
            'pipeline' => $summary['pipeline'],
            'kpis' => $summary['kpis'],
            'recentOrders' => $summary['recentOrders'],
            'baseCurrency' => (string) config('payment.currency'),
        ]);
    }
}
