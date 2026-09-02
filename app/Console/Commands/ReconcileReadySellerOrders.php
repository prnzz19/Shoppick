<?php

namespace App\Console\Commands;

use App\Models\SellerOrder;
use App\Services\ShipmentService;
use Illuminate\Console\Command;

class ReconcileReadySellerOrders extends Command
{
    protected $signature = 'logistics:reconcile-ready-orders {--dry-run : Report matching Seller Orders without changing data}';
    protected $description = 'Idempotently reconcile ready-to-ship Seller Orders with Logistics loads and notifications';

    public function handle(ShipmentService $shipments): int
    {
        $query = SellerOrder::where('status', 'ready_to_ship')->orderBy('id');
        $total = (clone $query)->count();
        $missing = (clone $query)->whereDoesntHave('shipment')->count();

        if ($this->option('dry-run')) {
            $this->info("{$total} ready Seller Order(s); {$missing} missing Logistics load(s).");
            return self::SUCCESS;
        }

        $reconciled = 0;
        $query->chunkById(100, function ($sellerOrders) use ($shipments, &$reconciled) {
            foreach ($sellerOrders as $sellerOrder) {
                $shipments->createForSellerOrder($sellerOrder);
                $reconciled++;
            }
        });

        $this->info("Reconciled {$reconciled} ready Seller Order(s); {$missing} missing load(s) created.");
        return self::SUCCESS;
    }
}
