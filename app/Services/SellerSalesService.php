<?php
namespace App\Services;

use App\Models\{SellerOrder,Store};
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerSalesService
{
    public function __construct(
        private ShopSalesReportService $reports,
        private SalesReportChartService $charts,
        private SalesReportExportService $exports,
    ) {}

    public function page(Request $request): array
    {
        $request->validate(['tab'=>'nullable|in:overview,transactions','export'=>'nullable|in:pdf']);
        if (!$request->filled('range')) $request->merge(['range'=>'30days']);
        $filters=$this->reports->filters($request);
        $store=$request->user()->store;
        $tab=$request->input('tab','overview');
        // Preserve the existing Sales KPI definitions and stored financial amounts.
        $base=SellerOrder::where('store_id',$store->id);
        $stats=[
            'today'=>(float)(clone $base)->where('status','completed')->whereDate('completed_at',today())->sum('seller_total'),
            'month'=>(float)(clone $base)->where('status','completed')->whereBetween('completed_at',[now()->startOfMonth(),now()->endOfMonth()])->sum('seller_total'),
            'completed'=>(float)(clone $base)->where('status','completed')->sum('seller_total'),
            'pending'=>(float)(clone $base)->whereNotIn('status',['completed','cancelled'])->sum('seller_total'),
            'commission'=>(float)(clone $base)->where('status','completed')->sum('commission_amount'),
        ];
        $orders=$this->reports->orders($store,$filters)->with('order')->latest('created_at')->orderByDesc('id');
        $orders=$tab==='transactions' ? $orders->paginate(15)->withPath(route('seller.sales.index',[],false))->withQueryString() : $orders->limit(5)->get();
        $chart=$this->charts->timeline($filters,Store::whereKey($store->id)->select('id'),$store,true);
        $statuses=$this->reports->orders($store,$filters)->select('status')->selectRaw('COUNT(*) as count')->groupBy('status')->get()->keyBy('status');
        $chart['statuses']=collect(SellerOrder::STATUSES)->filter(fn($s)=>isset($statuses[$s]))->map(fn($s)=>['label'=>(string)str($s)->headline(),'value'=>(int)$statuses[$s]->count])->values()->all();
        $categories=$this->reports->allocatedItems($filters)->where('store_id',$store->id)
            ->select('category_id','category_name')->selectRaw('SUM(allocated_sales) as sales')->groupBy('category_id','category_name')->orderByDesc('sales')->get();
        $chart['categories']=$categories->map(fn($r)=>['label'=>$r->category_name,'value'=>(float)$r->sales])->all();
        $earnings=$this->reports->orders($store,$filters,true)->selectRaw('COALESCE(SUM(subtotal),0) as gross, COALESCE(SUM(commission_amount),0) as commission, COALESCE(SUM(seller_total),0) as net')->first();
        $period=$filters['from'] ? $filters['from']->format('M d, Y').' - '.$filters['to']->format('M d, Y') : 'All time';
        return compact('store','tab','filters','stats','orders','chart','categories','period','earnings');
    }

    public function pdf(Request $request, array $pageData): Response
    {
        $filters=$pageData['filters'];
        $store=$pageData['store'];
        $reportOrders=$this->reports->orders($store,$filters)->with('order')->orderByDesc('created_at')->orderByDesc('id')->get();
        $data=$pageData+[
            'sellerName'=>$request->user()->name,
            'generated'=>now(),
            'reportOrders'=>$reportOrders,
        ];
        $from=$filters['from']?->format('Y-m-d') ?? 'All-Time';
        $to=$filters['to']?->format('Y-m-d');
        $period=$to ? $from.'_to_'.$to : $from;
        $filename='SHOPPICK_'.$store->slug.'_Sales_Report_'.$period.'.pdf';
        return $this->exports->pdf('seller.sales.pdf',$data,$filename);
    }
}
