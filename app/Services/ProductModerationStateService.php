<?php

namespace App\Services;

use App\Models\Product;

class ProductModerationStateService
{
    public const ATTENTION_STATUSES = ['pending_scan', 'flagged', 'under_review', 'scan_failed'];

    public function requiresMandatoryImageVerification(Product $product): bool
    {
        $text = implode(' ', [$product->name, strip_tags($product->description ?? ''),
            $product->category?->name, $product->category?->parent?->name]);
        return (bool) preg_match('/\b(weapons?|firearms?|handguns?|pistols?|rifles?|shotguns?|ammunition|grenades?|explosives?|switchblades?|combat knives|combat knife|stun guns?|tasers?)\b/iu', $text)
            || $product->moderationScans()->whereIn('status', ['flagged', 'under_review', 'rejected'])->exists()
            || $product->moderationScans()->where('status','!=','approved')->whereIn('detected_category',['weapon','firearm','firearms','ammunition','explosive','explosives'])->exists();
    }

    public function refresh(Product $product, bool $allowManualRelease = false): void
    {
        $product->refresh();
        $scans = $product->moderationScans()->get();
        $statuses = $scans->pluck('status');
        $mandatory = $this->requiresMandatoryImageVerification($product);
        $verified = $scans->isNotEmpty() && $scans->every(fn ($scan) =>
            $scan->status === 'approved' && ($scan->review_type === 'manual' || $scan->moderation_result === 'safe'));
        $hold = !$allowManualRelease && (
            filled($product->suspension_reason) ||
            ($product->moderation_status === 'rejected' && $scans->isEmpty()) ||
            $product->reports()->whereIn('status',['open','under_review','escalated'])
                ->where('source','!=','automated_moderation')->exists()
        );
        $status = match (true) {
            $scans->isEmpty() && in_array($product->moderation_status,['pending_scan','under_review','flagged','rejected','scan_failed']) => $product->moderation_status,
            $statuses->contains('rejected') => 'rejected',
            $statuses->contains('flagged') => 'flagged',
            $hold => in_array($product->moderation_status,['rejected','under_review','flagged']) ? $product->moderation_status : 'under_review',
            $statuses->contains('under_review') => 'under_review',
            $mandatory && !$verified => $statuses->contains('scan_failed') ? 'scan_failed' : 'under_review',
            $statuses->contains('pending_scan'), $statuses->contains('scanning') => 'pending_scan',
            $statuses->contains('scan_failed') => 'clean',
            $scans->isNotEmpty() => 'approved',
            default => 'clean',
        };
        $product->update([
            'moderation_status'=>$status,
            'is_active'=>in_array($status,['clean','approved'],true) && $product->publication_status === 'published' && $product->store?->isMarketplaceActive(),
            'suspension_reason'=>$allowManualRelease && in_array($status,['clean','approved'],true) ? null : ($product->suspension_reason ?: ($statuses->contains('flagged') ? 'Safety concern requires Admin review.' : null)),
        ]);
    }

    public function canRecoverOptionalFailure(Product $product): bool
    {
        if ($product->moderation_status !== 'scan_failed' || filled($product->suspension_reason)
            || $this->requiresMandatoryImageVerification($product)
            || $product->reports()->whereIn('status',['open','under_review','escalated'])->exists()) return false;
        $statuses = $product->moderationScans()->pluck('status');
        return $statuses->contains('scan_failed') && $statuses->every(fn ($status) => in_array($status,['scan_failed','approved'],true));
    }
    public function rescanAfterEdit(Product $product): void
    {
        foreach ($product->images()->get() as $image) {
            app(ProductImageModerationEnrollmentService::class)->rescan($image);
        }
        $this->refresh($product);
    }
}
