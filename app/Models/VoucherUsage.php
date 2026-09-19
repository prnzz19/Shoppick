<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherUsage extends Model
{
    use HasFactory;

    protected $fillable = ['voucher_id', 'user_id', 'order_id', 'seller_order_id', 'discount_amount', 'shipping_discount_amount', 'voucher_code', 'voucher_title', 'source_type', 'funding_source', 'used_at'];

    protected $casts = ['used_at'=>'datetime', 'discount_amount'=>'decimal:2', 'shipping_discount_amount'=>'decimal:2'];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder() { return $this->belongsTo(SellerOrder::class); }
}
