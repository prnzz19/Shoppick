<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    public const BUYER_SELLER = 'buyer_seller';
    public const SELLER_ADMIN = 'seller_admin';

    protected $fillable = ['type','identity_key','buyer_id','store_id','product_id','seller_order_id','created_by','last_message_at'];
    protected $casts = ['last_message_at' => 'datetime'];

    public function buyer() { return $this->belongsTo(User::class, 'buyer_id')->withTrashed(); }
    public function store() { return $this->belongsTo(Store::class)->withTrashed(); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function sellerOrder() { return $this->belongsTo(SellerOrder::class); }
    public function messages() { return $this->hasMany(ChatMessage::class, 'conversation_id'); }
    public function latestMessage() { return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany(); }
}
