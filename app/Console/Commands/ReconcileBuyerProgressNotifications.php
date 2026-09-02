<?php

namespace App\Console\Commands;

use App\Models\SellerOrder;
use App\Services\BuyerOrderNotificationService;
use Illuminate\Console\Command;

class ReconcileBuyerProgressNotifications extends Command
{
    protected $signature='orders:reconcile-buyer-progress-notifications {--dry-run : Report eligible records without creating notifications}';
    protected $description='Idempotently ensure Buyer notifications exist for current fulfillment milestones';

    public function handle(BuyerOrderNotificationService $notifications): int
    {
        $sellerOrders=SellerOrder::with('order.user')->where('status','ready_to_ship')->get();
        $total=$sellerOrders->count();
        if($this->option('dry-run')){$this->info("{$total} current Buyer milestone(s) eligible for reconciliation.");return self::SUCCESS;}
        foreach($sellerOrders as $sellerOrder)$notifications->send($sellerOrder->order,'ready_to_ship',$sellerOrder);
        $this->info("Reconciled {$total} current Buyer milestone(s).");
        return self::SUCCESS;
    }
}
