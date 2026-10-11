<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingController extends Controller
{
    public function index()
    {
        $store = auth()->user()->store;
        $vouchers = $store->vouchers()
            ->where('source_type', 'shop')
            ->withCount('usages')
            ->with(['products.primaryImage', 'products.category', 'usages.order'])
            ->latest()
            ->paginate(15);
        $categories = Category::whereHas('products', fn ($query) => $query
            ->where('store_id', $store->id)
            ->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name']);
        $oldScope = old('application_scope', 'entire_shop');
        $oldProductIds = $oldScope === 'selected_products'
            ? array_map('intval', (array) old('product_ids', []))
            : [];
        $selectedProducts = $oldProductIds
            ? $store->products()->whereIn('id', $oldProductIds)->get(['id', 'name', 'sku'])
            : collect();

        return view('seller.marketing.index', compact('vouchers', 'categories', 'selectedProducts'));
    }

    public function products(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $store = $request->user()->store;
        $query = $store->products()
            ->where('is_active', true)
            ->with(['primaryImage', 'category']);

        if (!empty($filters['q'])) {
            $term = '%' . mb_strtolower(trim($filters['q'])) . '%';
            $query->where(fn ($products) => $products
                ->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$term]));
        }

        if (!empty($filters['category'])) {
            $categoryExists = Category::where('id', $filters['category'])
                ->whereHas('products', fn ($products) => $products
                    ->where('store_id', $store->id)
                    ->where('is_active', true))
                ->exists();
            abort_unless($categoryExists, 404);
            $query->where('category_id', $filters['category']);
        }

        $products = $query->orderBy('name')->orderBy('id')->paginate(10);

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'image' => $product->primaryImage?->url,
                'category' => $product->category?->name,
            ]),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'from' => $products->firstItem(),
            'to' => $products->lastItem(),
            'total' => $products->total(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper($data['code']);
        $data['status'] = $request->input('status', 'active');
        $data['source_type'] = 'shop';
        $data['platform_scope'] = 'all_shops';
        $data['created_by'] = $request->user()->id;

        $voucher = $request->user()->store->vouchers()->create($data);
        $this->syncProducts($voucher, $request);

        return back()->with('success', 'Shop Voucher created successfully.');
    }

    public function update(Request $request, Voucher $voucher)
    {
        $this->own($voucher);
        $data = $this->validated($request, $voucher);
        $data['code'] = strtoupper($data['code']);
        $voucher->update($data);
        $this->syncProducts($voucher, $request);

        return back()->with('success', 'Voucher updated successfully.');
    }

    public function toggle(Voucher $voucher)
    {
        $this->own($voucher);
        $voucher->update(['status' => $voucher->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', 'Voucher status updated.');
    }

    private function validated(Request $request, ?Voucher $voucher = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', 'unique:vouchers,code' . ($voucher ? ',' . $voucher->id : '')],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:percent,fixed,free_shipping'],
            'value' => ['nullable', 'numeric', 'min:0', 'required_unless:type,free_shipping'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', 'in:active,inactive'],
            'application_scope' => ['required', 'in:entire_shop,selected_products'],
            'product_ids' => ['exclude_unless:application_scope,selected_products', 'required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->where(fn ($query) => $query
                ->where('store_id', $request->user()->store->id)
                ->where('is_active', true)
                ->whereNull('deleted_at'))],
        ]);

        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => 'Percentage discount may not exceed 100%.']);
        }

        if ($data['type'] === 'free_shipping') {
            $data['value'] = 0;
        }

        return $data;
    }

    private function syncProducts(Voucher $voucher, Request $request): void
    {
        if ($request->input('application_scope') !== 'selected_products') {
            $voucher->products()->sync([]);
            return;
        }

        $ids = $request->input('product_ids', []);
        $valid = $request->user()->store->products()->whereIn('id', $ids)->pluck('id');
        $voucher->products()->sync($valid);
    }

    private function own(Voucher $voucher): void
    {
        abort_unless($voucher->source_type === 'shop' && $voucher->store_id === auth()->user()->store->id, 403);
    }
}
