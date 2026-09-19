<?php

namespace App\Console\Commands;

use App\Jobs\ModerateProductImage;
use App\Models\ModerationScan;
use App\Services\ProductImageModerationEnrollmentService;
use Illuminate\Console\Command;

class ReconcileProductImageModeration extends Command
{
    protected $signature = 'moderation:reconcile-product-images
        {--dry-run : Report eligible and missing image records without changing data}
        {--dispatch : Dispatch the configured moderation scanner for newly created records}
        {--process-pending : Also process existing pending scans}';

    protected $description = 'Idempotently enroll eligible marketplace product images in moderation';

    public function handle(ProductImageModerationEnrollmentService $enrollment): int
    {
        if ($this->option('dry-run')) {
            $eligible = $enrollment->eligibleImages()->count();
            $missing = $enrollment->eligibleImages()->whereDoesntHave('moderationScans')->count();
            $statuses = ModerationScan::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
            $pending = (int) $statuses->get('pending_scan', 0);
            $approved = (int) $statuses->get('approved', 0);
            $flagged = (int) $statuses->get('flagged', 0);
            $failed = (int) $statuses->get('scan_failed', 0);
            $this->info("{$eligible} eligible image(s); {$missing} missing; {$pending} pending; {$approved} approved; {$flagged} flagged; {$failed} failed.");

            return self::SUCCESS;
        }

        $processed = 0;
        if ($this->option('process-pending')) {
            ModerationScan::where('status', 'pending_scan')->orderBy('id')->chunkById(100, function ($scans) use (&$processed) {
                foreach ($scans as $scan) {
                    config('services.image_moderation.queued')
                        ? ModerateProductImage::dispatch($scan->id)
                        : ModerateProductImage::dispatchSync($scan->id);
                    $processed++;
                }
            });
        }

        $result = $enrollment->reconcile((bool) $this->option('dispatch'));
        $this->info("Reconciled {$result['eligible']} eligible image(s); created {$result['created']} pending moderation record(s).");
        if ($this->option('process-pending')) {
            $this->info("Processed or queued {$processed} existing pending scan(s).");
        }

        return self::SUCCESS;
    }
}
