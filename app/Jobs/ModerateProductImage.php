<?php

namespace App\Jobs;

use App\Models\AdminActivityLog;
use App\Models\ModerationScan;
use App\Models\Report;
use App\Models\Role;
use App\Services\Moderation\ImageModerationService;
use App\Services\NotificationService;
use App\Services\ProductModerationStateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ModerateProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $scanId) {}

    public function handle(ImageModerationService $service, ?ProductModerationStateService $state = null): void
    {
        $state ??= app(ProductModerationStateService::class);
        $scan = ModerationScan::with(['product', 'image', 'store.user', 'store.sellerProfile'])->findOrFail($this->scanId);
        $scan->update(['status' => 'scanning', 'failure_message' => null]);

        try {
            $result = $service->scan(Storage::disk('public')->path($scan->image->path));
            $classification = $this->classification($result);
            $scan->update([
                'provider' => config('services.image_moderation.provider', 'local'),
                'provider_reference' => $result['reference'] ?? null,
                'status' => $classification,
                'detected_category' => $result['category'] ?? ($classification === 'approved' ? 'safe' : 'uncertain'),
                'moderation_result' => $classification === 'approved' ? 'safe' : ($classification === 'flagged' ? 'potential_policy_violation' : 'uncertain'),
                'confidence' => isset($result['confidence']) && is_numeric($result['confidence']) ? $result['confidence'] : null,
                'risk_level' => $result['risk_level'] ?? match ($classification) {
                    'flagged' => 'high',
                    'pending_scan' => 'medium',
                    default => 'low',
                },
                'decision' => $classification === 'approved' ? 'auto_approved' : null,
                'review_type' => $classification === 'approved' ? 'automatic' : null,
                'reviewed_at' => $classification === 'approved' ? now() : null,
            ]);

            if ($classification === 'flagged') {
                $this->flag($scan->fresh(['product', 'store', 'seller']));
            }

            $state->refresh($scan->product->fresh());
            AdminActivityLog::record('moderation.'.$classification, 'moderation_scan', $scan->id, ['actor' => 'system', 'provider' => $scan->provider]);
        } catch (\Throwable $exception) {
            $scan->update(['status' => 'scan_failed', 'moderation_result' => 'scan_failed', 'risk_level' => 'medium', 'failure_message' => 'Moderation provider temporarily unavailable.']);
            $state->refresh($scan->product->fresh());
            report($exception);
        }
    }

    private function classification(array $result): string
    {
        return match (strtolower((string) ($result['status'] ?? 'uncertain'))) {
            'safe', 'clean', 'approved' => 'approved',
            'flagged', 'harmful', 'prohibited', 'high_risk' => 'flagged',
            default => 'pending_scan',
        };
    }

    private function flag(ModerationScan $scan): void
    {
        $report = Report::where('target_type', get_class($scan->product))->where('target_id', $scan->product_id)
            ->whereIn('status', ['open', 'under_review', 'escalated'])->first();
        if ($report) {
            $report->increment('related_count');
        } else {
            Report::create([
                'report_number' => Report::number(), 'target_type' => get_class($scan->product), 'target_id' => $scan->product_id,
                'reason' => 'inappropriate_product_image',
                'description' => 'Automated moderation detected a possible '.str_replace('_', ' ', $scan->detected_category ?? 'policy violation').'.',
                'priority' => in_array($scan->risk_level, ['high', 'critical'], true) ? $scan->risk_level : 'high', 'source' => 'automated_moderation',
            ]);
        }
        foreach (Role::where('slug', 'admin')->with('users')->get() as $role) {
            foreach ($role->users as $user) {
                NotificationService::send($user->id, 'Product requires moderation review', "{$scan->product->name} was flagged for human review.", 'moderation', route('admin.moderation.show', $scan));
            }
        }
        if ($scan->seller_id) {
            NotificationService::send($scan->seller_id, 'Product image under review', 'Your product image is under review. We will notify you after an Admin decision.', 'moderation', route('seller.products.index'));
        }
    }
}
