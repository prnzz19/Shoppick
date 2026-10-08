<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSalesAnalyticsService;
use App\Services\SalesReportExportService;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request, AdminSalesAnalyticsService $analytics)
    {
        $data = $analytics->data($analytics->filters($request));
        if ($request->expectsJson()) {
            return response()->json(['content_html' => view('admin.analytics.content', $data)->render(), 'url' => route('admin.analytics.index', $data['filters']['query'] + ($request->has('page') ? ['page' => $request->integer('page')] : []))]);
        }

        return view('admin.analytics.index', $data + $analytics->options());
    }

    public function pdf(Request $request, AdminSalesAnalyticsService $analytics, SalesReportExportService $exports)
    {
        return $exports->pdf('admin.analytics.print', $analytics->data($analytics->filters($request), false, true), 'shoppick-sales-analytics.pdf');
    }

    public function print(Request $request, AdminSalesAnalyticsService $analytics)
    {
        return view('admin.analytics.print', $analytics->data($analytics->filters($request), false, true));
    }

    public function csv(Request $request, AdminSalesAnalyticsService $analytics, SalesReportExportService $exports)
    {
        $data = $analytics->data($analytics->filters($request), false, true);

        return response()->streamDownload(function () use ($data, $exports) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $write = function (array $row) use ($out, $exports) {
                fputcsv($out, array_map(fn ($v) => is_string($v) ? $exports->csvText($v) : $v, $row), ',', '"', '');
            };
            $write(['SHOPPICK Sales & Marketplace Analytics', $data['period']]);
            foreach ($data['filters']['query'] as $key => $value) {
                $write(['Filter', $key, $value]);
            }
            $write(['Metric', 'Current', 'Previous', 'Difference', 'Change']);
            foreach ($data['summary'] as $key => $value) {
                $write([$key, $value, $data['previous'][$key] ?? 'No comparable period', $data['changes'][$key]['difference'] ?? '', $data['changes'][$key]['label']]);
            }
            foreach (['shops' => 'Shops', 'categories' => 'Categories', 'products' => 'Products'] as $key => $title) {
                $write([$title]);
                $write(['Name', 'Sales', 'Orders', 'Units', 'Share %', 'Previous Sales']);
                foreach ($data[$key] as $row) {
                    $write([$row->name, $row->sales, $row->orders, $row->units, $row->share_percent, $data['previous'] ? $row->previous_sales : 'No comparable period']);
                }
            }
            $write(['Status', 'Orders', 'Share %', 'Previous Orders']);
            foreach ($data['statusRows'] as $row) {
                $write([$row->status, $row->orders, $row->share_percent, $row->previous_orders ?? 'No comparable period']);
            }
            $write(['Cancellations', $data['cancellations']['current'], $data['cancellations']['previous'] ?? 'No comparable period']);
            $write(['Cancellation rate %', $data['cancellations']['rate'], $data['cancellations']['previous_rate'] ?? 'No comparable period']);
            $write(['Shops with no sales', $data['counts']['no_sales']]);
            $write(['Products with no sales', $data['noSaleProducts']['count']]);
            $write(['Currently out of stock among no-sales products', $data['noSaleProducts']['out_of_stock']]);
            foreach ($data['insights'] as $insight) {
                $write(['Insight', $insight['priority'], $insight['text']]);
            }
            fclose($out);
        }, 'shoppick-sales-analytics.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
