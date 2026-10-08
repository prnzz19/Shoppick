<nav class="my-5 flex gap-3" aria-label="Archive filters">
    @foreach(['normal'=>'Current', 'archived'=>'Archived'] as $key=>$label)
        @php
            $params = array_diff_key($queryParams, array_flip(['tab','page']));
            if ($key === 'archived') $params['tab'] = 'archived';
            $selected = ($key === 'archived') === $archived;
        @endphp
        <a class="rounded-xl border px-4 py-2 text-sm font-semibold {{ $selected ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-slate-200 bg-white text-slate-500' }}" href="{{ route('admin.sellers.index',$params) }}" @if($selected) aria-current="page" @endif>{{ $label }} <span>{{ $archiveCounts[$key] }}</span></a>
    @endforeach
</nav>
