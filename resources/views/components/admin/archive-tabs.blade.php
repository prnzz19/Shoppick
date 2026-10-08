@props(['route', 'counts', 'archived'])
<nav class="my-5 flex gap-3" aria-label="Archive filters">
    @foreach(['normal' => 'Current', 'archived' => 'Archived'] as $key => $label)
        <a class="rounded-xl border px-4 py-2 text-sm font-semibold {{ ($key === 'archived') === $archived ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-slate-200 bg-white text-slate-500' }}" href="{{ route($route, array_merge(request()->except(['tab', 'page']), $key === 'archived' ? ['tab' => 'archived'] : [])) }}">{{ $label }} <span>{{ $counts[$key] }}</span></a>
    @endforeach
</nav>
