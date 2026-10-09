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
        $scan = ModerationScan::with(['product','image'])->find($this->scanId);
        if (!$scan?->product || !$scan->image || in_array($scan->status,['rejected','flagged','under_review'],true) || $scan->review_type === 'manual') return;
        $context = [$scan->image->path, $scan->product->only(['name','description','category_id'])];
        $scan->update(['status'=>'scanning','failure_message'=>null]);
        $failure = null;
        try {
            $result = $service->scan(Storage::disk('public')->path($scan->image->path));
            $classification = $this->classification($result);
        } catch (\Throwable $exception) {
            $result = [];
            $classification = 'scan_failed';
            $failure = $exception;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($scan, $context, $result, $classification, $state) {
            $current = ModerationScan::with(['product','image'])->lockForUpdate()->find($scan->id);
            if (!$current?->product || !$current->image || $current->review_type === 'manual' ||
                in_array($current->status,['flagged','rejected','under_review'],true) ||
                $context !== [$current->image->path,$current->product->only(['name','description','category_id'])]) return;
            $current->update([
                'provider'=>config('services.image_moderation.provider','local'),
                'provider_reference'=>$result['reference'] ?? null,
                'status'=>$classification,
                'detected_category'=>$result['category'] ?? ($classification === 'approved' ? 'safe' : 'uncertain'),
                'moderation_result'=>match($classification) {
                    'approved'=>($result['content_verified'] ?? true) ? 'safe' : 'file_validated',
                    'flagged'=>'potential_policy_violation',
                    'scan_failed'=>'scan_failed',
                    default=>'uncertain',
                },
                'confidence'=>isset($result['confidence']) && is_numeric($result['confidence']) ? $result['confidence'] : null,
                'risk_level'=>$result['risk_level'] ?? ($classification === 'flagged' ? 'high' : 'low'),
                'decision'=>$classification === 'approved' ? 'auto_approved' : null,
                'review_type'=>$classification === 'approved' ? 'automatic' : null,
                'reviewed_at'=>$classification === 'approved' ? now() : null,
                'failure_message'=>$classification === 'scan_failed' ? 'Moderation provider temporarily unavailable.' : null,
            ]);
            $state->refresh($current->product);
            if ($classification === 'flagged') $this->flag($current->fresh(['product','store','seller']));
            AdminActivityLog::record('moderation.'.$classification,'moderation_scan',$current->id,['actor'=>'system','provider'=>$current->provider]);
        });
        if ($failure) report($failure);
    }

    private function classification(array $result): string
    {
        return match (strtolower((string) ($result['status'] ?? 'uncertain'))) {
            'safe', 'clean', 'approved' => 'approved',
            'flagged', 'harmful', 'prohibited', 'high_risk' => 'flagged',
            'failed', 'timeout', 'unavailable', 'error', 'scan_failed' => 'scan_failed',
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
