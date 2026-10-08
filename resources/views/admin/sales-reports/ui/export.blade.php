<details class="report-export">
    <summary>@include('admin.sales-reports.ui.icon',['icon'=>'export']) Export Report <span aria-hidden="true">⌄</span></summary>
    <div class="report-export-menu">
        @foreach(['pdf'=>'Export PDF','print'=>'Print Report','csv'=>'Export CSV'] as $format=>$label)
        <a data-report-export="{{ $format }}" href="{{ route($exportRoute.'.'.$format,$exportParams) }}" @if($format==='print') target="_blank" rel="noopener" @endif>@include('admin.sales-reports.ui.icon',['icon'=>$format]) {{ $label }}</a>
        @endforeach
    </div>
</details>
