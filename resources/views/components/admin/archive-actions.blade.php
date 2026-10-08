@props(['record', 'prefix', 'label'])
@if(auth()->user()->hasPermissionTo('manage_sellers'))
    <div class="inline-flex items-center gap-2 whitespace-nowrap">
        @foreach($record->archived_at ? ['restore', 'force-delete'] : ['archive'] as $action)
            @php
                $verb = match ($action) { 'restore' => 'Restore', 'force-delete' => 'Permanently Delete', default => 'Archive' };
                $message = match ($action) {
                    'restore' => 'Restore this '.$label.'? Its previous business status will be preserved. Separately archived records will remain archived.',
                    'force-delete' => 'Permanently delete this archived '.$label.'? This action cannot be undone. Related marketplace history will block deletion.',
                    default => 'Archive this '.$label.'? Its account, business status and historical data will be preserved. Buyer access remains available.',
                };
            @endphp
            <form method="POST" action="{{ route($prefix.'.'.$action, $record->id) }}" data-entity-archive-form data-confirm-title="{{ $verb }} {{ $label }}?" data-confirm-message="{{ $message }}" data-confirm-action="{{ $verb }} {{ $label }}" data-confirm-type="{{ $action === 'force-delete' ? 'danger' : ($action === 'restore' ? 'success' : 'warning') }}">
                @csrf @method($action === 'force-delete' ? 'DELETE' : 'PATCH')
                <button type="submit" class="rounded-lg px-3 py-2 text-sm font-semibold {{ $action === 'force-delete' ? 'text-rose-600 hover:bg-rose-50' : ($action === 'restore' ? 'text-brand-700 hover:bg-brand-50' : 'text-accent-600 hover:bg-accent-50') }}">{{ $action === 'force-delete' ? 'Delete' : $verb }}</button>
            </form>
        @endforeach
    </div>
@endif
@once
@push('scripts')
<script>
document.addEventListener('submit', function (event) {
    const form = event.target.closest('form[data-entity-archive-form]');
    if (!form || form.dataset.confirmed !== 'true') return;
    if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
    form.dataset.submitting = 'true';
    form.querySelector('button[type="submit"]').disabled = true;
    document.querySelector('[data-confirm-submit]').disabled = true;
});
</script>
@endpush
@endonce
