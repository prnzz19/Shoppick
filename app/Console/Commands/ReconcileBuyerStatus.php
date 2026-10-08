<?php

namespace App\Console\Commands;

use App\Services\BuyerStatusReconciliation;
use Illuminate\Console\Command;

class ReconcileBuyerStatus extends Command
{
    protected $signature = 'users:reconcile-buyer-status {--dry-run : Report only (the default)} {--apply : Apply eligible repairs after review} {--user=* : Limit to reviewed user IDs}';

    protected $description = 'Conservatively report or repair Buyers blocked by the old registration approval rule';

    public function handle(BuyerStatusReconciliation $reconciliation): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --apply.');

            return self::FAILURE;
        }
        $ids = $this->option('user');
        foreach ($ids as $id) {
            if (! ctype_digit((string) $id)) {
                $this->error('--user must contain numeric user IDs.');

                return self::FAILURE;
            }
        }
        if ($this->option('apply') && ! $ids) {
            $this->error('--apply requires explicit reviewed --user IDs.');

            return self::FAILURE;
        }
        $query = $reconciliation->candidates()->when($ids, fn ($query) => $query->whereIn('id', $ids));
        $eligible = 0;
        $skipped = 0;
        $rows = [];
        foreach ($query->lazyById(100) as $user) {
            $reason = $reconciliation->exclusion($user);
            $action = $reason === null ? 'Would activate' : 'Skipped';
            if ($reason === null) {
                $eligible++;
                if ($this->option('apply')) {
                    $action = $reconciliation->apply($user->id) ? 'Activated' : 'Skipped (state changed)';
                }
            } else {
                $skipped++;
            }
            $rows[] = [$user->id, implode(', ', $user->roles->pluck('slug')->all()), $user->registration_status,
                $user->is_active ? 'active' : 'inactive', $action,
                $reason ?? 'Completed normal registration; no review, archive or admin intervention evidence'];
        }
        $this->table(['ID', 'Roles', 'Registration', 'Account', 'Action', 'Reason'], $rows);
        $this->info("Eligible: {$eligible}; skipped: {$skipped}.");
        if (! $this->option('apply')) {
            $this->info('DRY RUN ONLY. No database records changed.');
        }

        return self::SUCCESS;
    }
}
