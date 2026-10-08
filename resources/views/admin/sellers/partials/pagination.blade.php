<div class="mt-4"><p class="mb-2 text-sm text-slate-500">Showing {{ $sellers->firstItem() ?? 0 }}–{{ $sellers->lastItem() ?? 0 }} of {{ $sellers->total() }} sellers</p>{{ $sellers->links() }}</div>
