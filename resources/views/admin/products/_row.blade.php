<tr>
    <td class="table-td">
        <div class="flex items-center gap-3">
            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                @if($product->getMainImageAttribute())<img src="{{ asset('storage/'.$product->getMainImageAttribute()) }}" class="h-full w-full object-cover" alt="">@endif
            </div>
            <div class="min-w-0">
                <p class="line-clamp-1 font-medium text-navy-800">{{ $product->name }}</p>
                <p class="text-xs text-slate-500">SKU: {{ $product->sku ?? '—' }}</p>
            </div>
        </div>
    </td>
    <td class="table-td">{{ $product->category?->name ?? 'Uncategorized' }}</td>
    <td class="table-td">
        <p class="font-semibold text-navy-800">₱{{ number_format($product->salePrice(), 2) }}</p>
        @if($product->hasDiscount())<p class="text-xs text-slate-400 line-through">₱{{ number_format($product->originalPrice(), 2) }}</p>@endif
    </td>
    <td class="table-td"><span class="{{ $product->isOutOfStock() ? 'font-semibold text-rose-600' : ($product->isLowStock() ? 'font-semibold text-accent-600' : 'text-navy-700') }}">{{ $product->stock }}</span></td>
    <td class="table-td">{{ number_format($product->sold_count) }}</td>
    <td class="table-td">
        @if($product->trashed())
            <span class="badge bg-slate-100 text-slate-600">Archived</span>
        @else
            @php
                $displayStatus = $product->sellerStatus();
                $statusClass = match($displayStatus) {
                    'Active' => 'bg-leaf-100 text-leaf-500',
                    'Under Moderation' => 'bg-sun-100 text-sun-500',
                    'Rejected' => 'bg-rose-100 text-rose-600',
                    'Out of Stock' => 'bg-rose-100 text-rose-600',
                    'Review Required', 'Draft', 'Suspended', 'Store Suspended' => 'bg-slate-100 text-slate-600',
                    default => 'bg-slate-100 text-slate-600',
                };
            @endphp
            <form method="POST" action="{{ route('admin.products.toggle', $product->id) }}" class="inline">@csrf<button type="submit" class="badge {{ $statusClass }}">{{ $displayStatus }}</button></form>
        @endif
        @if($product->is_featured)<span class="badge ml-1 bg-accent-100 text-accent-600">Featured</span>@endif
    </td>
    <td class="table-td">
        @if($product->trashed())
            <span class="text-xs text-slate-400">Archived {{ $product->deleted_at?->format('M d, Y') }}</span>
        @else
            <div class="flex justify-end gap-1">
                <a href="{{ route('products.show', $product->slug) }}" class="p-2 text-slate-400 hover:text-brand-600" title="View" target="_blank"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm7-3a9 9 0 01-14 0M22 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></a>
                <a href="{{ route('admin.products.edit', $product->id) }}" class="p-2 text-slate-400 hover:text-brand-600" title="Edit"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
                <form method="POST" action="{{ route('admin.products.destroy', $product->id) }}" data-confirm-title="Delete this product?" data-confirm-message="This action may permanently remove the selected product." data-confirm-action="Delete" data-confirm-type="danger">@csrf @method('DELETE')<button type="submit" class="p-2 text-slate-400 hover:text-rose-600" title="Delete"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
            </div>
        @endif
    </td>
</tr>
