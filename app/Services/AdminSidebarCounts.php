<?php

namespace App\Services;

use App\Models\ModerationScan;
use App\Models\Product;
use App\Models\Report;
use App\Models\SellerApplication;
use App\Models\SellerOrder;
use App\Models\User;

class AdminSidebarCounts
{
    /**
     * Return only actionable counts shown in the Admin navigation.
     *
     * @return array<string, int>
     */
    public function get(): array
    {
        return [
            'users' => User::query()
                ->where('registration_type', 'buyer')
                ->where('registration_status', 'pending')
                ->count(),
            'applications' => SellerApplication::query()
                ->where('status', 'pending')
                ->count(),
            'inventory' => Product::query()
                ->where('stock', '>', 0)
                ->where(fn ($query) => $query
                    ->whereColumn('stock', '<=', 'low_stock_threshold')
                    ->orWhere('stock', '<=', 5))
                ->count(),
            'orders' => SellerOrder::query()
                ->whereIn('status', SellerOrder::TAB_GROUPS['to_ship'])
                ->count(),
            'reports' => Report::query()
                ->whereIn('status', ['open', 'under_review', 'escalated'])
                ->count(),
            'moderation' => ModerationScan::query()
                ->whereIn('status', ProductModerationStateService::ATTENTION_STATUSES)
                ->whereHas('product')
                ->whereHas('image')
                ->distinct('product_id')
                ->count('product_id'),
        ];
    }
}
