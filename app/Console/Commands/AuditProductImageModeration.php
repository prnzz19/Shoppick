<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;

class AuditProductImageModeration extends Command
{
    protected $signature = 'moderation:audit-shops';

    protected $description = 'Report why each shop is included in or excluded from Product Image Moderation';

    public function handle(): int
    {
        $rows = Store::withTrashed()->with(['user:id,name,email,is_active', 'sellerProfile:id,status'])
            ->withCount(['products', 'products as image_product_count' => fn ($products) => $products->whereHas('images'), 'products as moderation_record_count' => fn ($products) => $products->whereHas('images.moderationScans')])
            ->orderBy('id')->get()->map(function (Store $store) {
                $reason = match (true) {
                    $store->trashed() => 'NO — shop deleted',
                    $store->status !== 'active' => 'NO — shop not active',
                    ! $store->user?->is_active => 'NO — seller inactive',
                    $store->sellerProfile?->status !== 'approved' => 'NO — seller application not approved',
                    $store->products_count === 0 => 'NO — no products',
                    $store->image_product_count === 0 => 'NO — no product images',
                    default => 'YES',
                };

                return [$store->id, $store->name, $store->user?->email ?? '—', $store->status, $store->sellerProfile?->status ?? '—', $store->products_count, $store->image_product_count, $store->moderation_record_count, $reason];
            });

        $this->table(['ID', 'Shop', 'Seller', 'Status', 'Approval', 'Products', 'With images', 'With scans', 'Eligible / reason'], $rows);

        return self::SUCCESS;
    }
}
