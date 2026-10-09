<?php
namespace App\Console\Commands;

use App\Models\{Product, AdminActivityLog};
use App\Services\ProductModerationStateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileOptionalImageFailures extends Command
{
    protected $signature = 'products:reconcile-optional-scans {--apply : Apply eligible recoveries; default is dry run}';
    protected $description = 'Recover stale optional scan failures without clearing safety or manual-review holds';

    public function handle(ProductModerationStateService $state): int
    {
        $eligible = 0;
        Product::where('moderation_status','scan_failed')->orderBy('id')->chunkById(100, function ($products) use ($state, &$eligible) {
            foreach ($products as $product) {
                DB::transaction(function () use ($product, $state, &$eligible) {
                    $current = Product::lockForUpdate()->find($product->id);
                    if (!$current || !$state->canRecoverOptionalFailure($current)) return;
                    $eligible++;
                    $this->line('Eligible product ID: '.$current->id);
                    if (!$this->option('apply')) return;
                    $before = $current->only(['moderation_status','is_active']);
                    $state->refresh($current);
                    AdminActivityLog::record('moderation.optional_failure_reconciled',Product::class,$current->id,
                        ['before'=>$before,'after'=>$current->fresh()->only(['moderation_status','is_active'])]);
                });
            }
        });
        $this->info(($this->option('apply') ? 'Recovered: ' : 'Dry run eligible: ').$eligible);
        return self::SUCCESS;
    }
}