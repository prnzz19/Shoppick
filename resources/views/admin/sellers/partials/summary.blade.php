<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach(['Total Sellers'=>number_format($summary->sellers), 'Active Shops'=>number_format($summary->active_shops), 'Total Sales'=>'₱'.number_format($summary->sales,2), 'Orders'=>number_format($summary->orders)] as $label=>$value)
        <section class="card p-4">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-xl font-bold text-navy-900">{{ $value }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ in_array($label,['Total Sales','Orders']) ? 'Matching sellers · selected period' : 'Matching sellers · current shop state' }}</p>
        </section>
    @endforeach
</div>
