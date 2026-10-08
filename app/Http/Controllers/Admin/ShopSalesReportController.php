<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\ShopSalesReportService;
use App\Services\SalesReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopSalesReportController extends Controller
{
    public function __construct(private ShopSalesReportService $reports) {}

    private function data(Request $request, Store $shop): array
    {
        return $this->reports->build($shop,$this->reports->filters($request));
    }

    public function show(Request $request, Store $shop)
    {
        return view('admin.shops.sales-report.index',$this->data($request,$shop));
    }

    public function print(Request $request, Store $shop)
    {
        return response()->view('admin.shops.sales-report.document',$this->data($request,$shop)+['pdf'=>false])
            ->header('Cache-Control','private, no-store');
    }

    public function pdf(Request $request, Store $shop)
    {
        $data = $this->data($request,$shop);
        return app(SalesReportExportService::class)->pdf('admin.shops.sales-report.document',$data,$this->filename($shop,$data['filters'],'pdf'));
    }

    public function csv(Request $request, Store $shop)
    {
        $filters = $this->reports->filters($request);
        $shop->loadMissing('user:id,name');
        $orders = $this->reports->orders($shop,$filters)->with(['order.user:id,name','items:id,seller_order_id,product_name,quantity'])->withSum('items','quantity');
        return response()->streamDownload(function () use ($shop,$filters,$orders) {
            $stream = fopen('php://output','w');
            fwrite($stream,"\xEF\xBB\xBF");
            $write = fn ($row) => fputcsv($stream,$row,',','"','');
            $write(['Shop','Seller','Report Start','Report End','Order Number','Order Date','Completed Date','Buyer','Products / Quantities','Units','Order Status','Seller Order Total (PHP)','Recognized Completed Sales (PHP)','Currency']);
            foreach ($orders->lazyById(200) as $order) {
                $write([$this->csvText($shop->name),$this->csvText($shop->user?->name ?? 'Unavailable seller'),
                    $filters['from']?->format('Y-m-d') ?? 'All Time',$filters['to']?->format('Y-m-d') ?? '',
                    $this->csvText($order->seller_order_number),$order->created_at->format('Y-m-d H:i:s'),$order->completed_at?->format('Y-m-d H:i:s') ?? '',
                    $this->csvText($order->order?->buyer_name ?: ($order->order?->user?->name ?? 'Unavailable buyer')),
                    $this->csvText($order->items->map(fn ($item) => $item->product_name.' × '.$item->quantity)->implode('; ')),
                    (int)($order->items_sum_quantity ?? 0),$order->status,number_format($order->seller_total,2,'.',''),
                    number_format($order->status === 'completed' ? $order->seller_total : 0,2,'.',''), 'PHP']);
            }
            fclose($stream);
        },$this->filename($shop,$filters,'csv'),['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }

    private function csvText(string $value): string
    {
        // Spreadsheet quoting alone does not prevent formulas in buyer/product names.
        return app(SalesReportExportService::class)->csvText($value);
    }

    private function filename(Store $shop, array $filters, string $extension): string
    {
        $name = Str::slug($shop->name) ?: 'shop-'.$shop->id;
        $period = $filters['from'] ? $filters['from']->format('Y-m-d').'_to_'.$filters['to']->format('Y-m-d') : 'All-Time';
        return 'SHOPPICK_'.substr($name,0,100).'_Sales_Report_'.$period.'.'.$extension;
    }
}
