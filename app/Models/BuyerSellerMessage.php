<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerSellerMessage extends Model
{
    protected $table = 'buyer_seller_messages';

    protected $fillable = [
        'conversation_id', 'sender_id', 'body', 'context_type',
        'product_id', 'seller_order_id', 'context_key', 'read_at', 'client_token',
    ];

    protected $casts = ['read_at' => 'datetime'];

    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BuyerSellerMessageAttachment::class, 'message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(BuyerSellerConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }
}
