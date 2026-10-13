<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BuyerSellerConversation extends Model
{
    protected $table = 'buyer_seller_conversations';

    protected $fillable = ['buyer_id', 'store_id', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BuyerSellerMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(BuyerSellerMessage::class, 'conversation_id')->latestOfMany();
    }
}
