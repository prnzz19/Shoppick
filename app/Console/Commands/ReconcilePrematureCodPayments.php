<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcilePrematureCodPayments extends Command
{
    protected $signature='payments:reconcile-premature-cod {--dry-run : Report clearly premature COD settlements without changing data}';
    protected $description='Safely restore pre-delivery COD payments with no collection evidence to pending';

    public function handle(): int
    {
        $orders=Order::with('payments')->where('payment_method','cod')->whereNull('paid_at')
            ->whereNotIn('status',['delivered','completed','refunded'])->get()->filter(function($order){
                $payment=$order->payments->sortByDesc('id')->first();
                return $payment?->status==='paid'&&is_null($payment->collected_at);
            });
        if($this->option('dry-run')){$this->info($orders->count().' clearly premature COD payment(s) found.');return self::SUCCESS;}
        foreach($orders as $order)DB::transaction(function()use($order){
            $payment=$order->payments()->lockForUpdate()->latest('id')->first();
            if($payment?->status==='paid'&&is_null($payment->collected_at)&&is_null($order->paid_at)){
                $payment->update(['status'=>'pending','paid_at'=>null]);
                $order->update(['payment_status'=>'cod','paid_at'=>null]);
            }
        });
        $this->info('Reconciled '.$orders->count().' clearly premature COD payment(s).');
        return self::SUCCESS;
    }
}
