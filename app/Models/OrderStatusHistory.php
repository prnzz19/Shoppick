<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    protected $fillable = ['order_id', 'seller_order_id', 'changed_by', 'status', 'note'];
    public function order() { return $this->belongsTo(Order::class); }
    public function sellerOrder() { return $this->belongsTo(SellerOrder::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
