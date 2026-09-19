<?php

namespace App\Services;

use App\Models\Product;

class ProductModerationStateService
{
    public const ATTENTION_STATUSES = ['pending_scan', 'flagged', 'under_review', 'scan_failed'];

    public function refresh(Product $product): void
    {
        $statuses = $product->moderationScans()->pluck('status');
        $status = match (true) {
            $statuses->contains('rejected') => 'rejected',
            $statuses->contains('flagged') => 'flagged',
            $statuses->contains('under_review'), $statuses->contains('pending_scan'), $statuses->contains('scanning') => 'pending_scan',
            $statuses->contains('scan_failed') => 'scan_failed',
            $statuses->isNotEmpty() && $statuses->every(fn ($value) => $value === 'approved') => 'approved',
            default => 'pending_scan',
        };

        $product->update([
            'moderation_status' => $status,
            'is_active' => $status === 'approved' && $product->publication_status === 'published' && $product->store?->isMarketplaceActive(),
            'suspension_reason' => $status === 'rejected' ? $product->suspension_reason : null,
        ]);
    }
}
