<?php

namespace App\Services;

use App\Jobs\ModerateProductImage;
use App\Models\ModerationScan;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductImageModerationEnrollmentService
{
    public function eligibleImages(): Builder
    {
        return ProductImage::query()
            ->whereHas('product.store', fn (Builder $store) => $store->marketplaceActive());
    }

    public function enroll(ProductImage $image, bool $dispatch = true): ?ModerationScan
    {
        $image->loadMissing('product.store.user', 'product.store.sellerProfile');
        $product = $image->product;

        if (! $product || $product->trashed() || ! $product->store?->isMarketplaceActive()) {
            return null;
        }

        [$scan, $created] = DB::transaction(function () use ($image, $product) {
            $scan = ModerationScan::firstOrCreate(
                ['product_image_id' => $image->id],
                [
                    'product_id' => $product->id,
                    'seller_id' => $product->store->user_id,
                    'store_id' => $product->store_id,
                    'provider' => config('services.image_moderation.provider', 'local'),
                    'status' => 'pending_scan',
                    'risk_level' => 'low',
                ]
            );

            if ($scan->wasRecentlyCreated) {
                $product->update(['moderation_status' => 'pending_scan', 'is_active' => false]);
            }

            return [$scan, $scan->wasRecentlyCreated];
        });

        if ($created && $dispatch) {
            if (config('services.image_moderation.queued')) {
                ModerateProductImage::dispatch($scan->id)->afterCommit();
            } else {
                DB::afterCommit(fn () => ModerateProductImage::dispatchSync($scan->id));
            }
        }

        return $scan;
    }

    public function rescan(ProductImage $image): void
    {
        $scan = $image->moderationScans()->first();
        if (!$scan) { $this->enroll($image); return; }
        // A replacement or retry must never erase a safety flag or human rejection.
        if (in_array($scan->status, ['flagged','under_review','rejected'], true)) return;
        $scan->update(['status'=>'pending_scan','moderation_result'=>null,'decision'=>null,
            'review_type'=>null,'reviewed_by'=>null,'reviewed_at'=>null,'failure_message'=>null]);
        app(ProductModerationStateService::class)->refresh($image->product);
        if (config('services.image_moderation.queued')) {
            ModerateProductImage::dispatch($scan->id)->afterCommit();
        } else {
            DB::afterCommit(fn () => ModerateProductImage::dispatchSync($scan->id));
        }
    }

    public function reconcile(bool $dispatch = false): array
    {
        $eligible = $this->eligibleImages()->count();
        $missingQuery = $this->eligibleImages()->whereDoesntHave('moderationScans');
        $missing = (clone $missingQuery)->count();
        $created = 0;

        $missingQuery->orderBy('product_images.id')->chunkById(100, function ($images) use ($dispatch, &$created) {
            foreach ($images as $image) {
                if ($this->enroll($image, $dispatch)?->wasRecentlyCreated) {
                    $created++;
                }
            }
        }, 'product_images.id', 'id');

        return compact('eligible', 'missing', 'created');
    }
}
