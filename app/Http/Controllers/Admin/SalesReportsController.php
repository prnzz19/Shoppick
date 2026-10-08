<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\{MarketplaceSalesReportService, SalesReportExportService};
use Illuminate\Http\Request;

class SalesReportsController extends Controller
{
    public function __construct(private MarketplaceSalesReportService $reports, private SalesReportExportService $exports) {}

    public function index(Request $request)
    {
        $data = $this->reports->data($this->reports->filters($request));
        $data['shopOptions'] = Store::orderBy('name')->get(['id','name']);
        return view('admin.sales-reports.index',$data);
    }

    public function filter(Request $request)
    {
        $data = $this->reports->data($this->reports->filters($request));
        $parts = [];
        foreach (['summary','table','pagination','period','exports'] as $part) $parts[$part.'_html'] = view('admin.sales-reports.'.$part,$data)->render();
        $query = $data['filters']['query'];
        if ($data['shops']->currentPage() > 1) $query['page'] = $data['shops']->currentPage();
        return response()->json($parts+['url'=>route('admin.sales-reports.index',$query),'total'=>$data['shops']->total(),'page'=>$data['shops']->currentPage()])->header('Cache-Control','private, no-store');
    }

    private function exportData(Request $request): array
    {
        return $this->reports->data($this->reports->filters($request),true);
    }

    private function filename(array $filters, string $format): string
    {
        $period = $filters['from'] ? $filters['from']->format('Y-m-d').'_to_'.$filters['to']->format('Y-m-d') : 'All-Time';
        return 'SHOPPICK_Seller_Sales_Report_'.$period.'.'.$format;
    }

    public function print(Request $request)
    {
        return response()->view('admin.sales-reports.document',$this->exportData($request)+['pdf'=>false])->header('Cache-Control','private, no-store');
    }

    public function pdf(Request $request)
    {
        $data = $this->exportData($request);
        return $this->exports->pdf('admin.sales-reports.document',$data,$this->filename($data['filters'],'pdf'));
    }

    public function csv(Request $request)
    {
        $data = $this->exportData($request);
        return response()->streamDownload(function () use ($data) {
            $stream = fopen('php://output','w');
            fwrite($stream,"\xEF\xBB\xBF");
            $write = fn ($row) => fputcsv($stream,$row,',','"','');
            $write(['Row Type','Shop','Seller','Period','Generated','Orders','Sales (PHP)','Units Sold','Average Order (PHP)','Performance','Previous Sales (PHP)','Sales Change (%)','Currency']);
            foreach ($data['shops'] as $shop) {
                $percent = $data['filters']['from'] && $shop->previous_sales > 0 ? ($shop->sales-$shop->previous_sales)/$shop->previous_sales*100 : null;
                $write(['Shop',$this->exports->csvText($shop->name),$this->exports->csvText($shop->seller_name ?? 'Unavailable seller'),$data['period'],$data['generated']->toIso8601String(),(int)$shop->orders,number_format($shop->sales,2,'.',''),(int)$shop->units,number_format($shop->average,2,'.',''),$shop->performance,$data['filters']['from'] ? number_format($shop->previous_sales,2,'.','') : '',$percent === null ? '' : number_format($percent,1,'.',''),'PHP']);
            }
            $s = $data['summary'];
            $write(['Totals','All matching shops','',$data['period'],$data['generated']->toIso8601String(),$s['orders'],number_format($s['sales'],2,'.',''),$s['units'],number_format($s['average'],2,'.',''),'','','','PHP']);
            fclose($stream);
        },$this->filename($data['filters'],'csv'),['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }
}
