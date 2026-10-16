<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceChatService
{
    public function buyerConversation(User $buyer, Store $store, ?Product $product = null, ?SellerOrder $sellerOrder = null): ChatConversation
    {
        abort_unless($buyer->hasRole('buyer'), 403);
        abort_if($store->user_id === $buyer->id, 422, 'You cannot message your own shop.');
        abort_unless($store->isMarketplaceActive(), 404);
        if ($product) abort_unless($product->store_id === $store->id && Product::publiclyVisible()->whereKey($product->id)->exists(), 404);
        if ($sellerOrder) abort_unless($sellerOrder->store_id === $store->id && $sellerOrder->order()->where('user_id', $buyer->id)->exists(), 404);

        $key = "buyer:{$buyer->id}:store:{$store->id}";
        try {
            return DB::transaction(function () use ($key, $buyer, $store, $product, $sellerOrder) {
                $conversation = ChatConversation::firstOrCreate(['identity_key' => $key], [
                    'type' => ChatConversation::BUYER_SELLER, 'buyer_id' => $buyer->id,
                    'store_id' => $store->id, 'created_by' => $buyer->id,
                ]);
                $updates = [];
                if ($product) $updates['product_id'] = $product->id;
                if ($sellerOrder) $updates['seller_order_id'] = $sellerOrder->id;
                if ($updates) $conversation->update($updates);
                return $conversation->fresh();
            });
        } catch (UniqueConstraintViolationException) {
            return ChatConversation::where('identity_key', $key)->firstOrFail();
        }
    }

    public function supportConversation(User $seller): ChatConversation
    {
        abort_unless($seller->hasApprovedSellerAccess(), 403);
        $store = $seller->store;
        $key = "support:store:{$store->id}";
        try {
            return ChatConversation::firstOrCreate(['identity_key' => $key], [
                'type' => ChatConversation::SELLER_ADMIN, 'store_id' => $store->id, 'created_by' => $seller->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return ChatConversation::where('identity_key', $key)->firstOrFail();
        }
    }

    public function canAccess(User $user, ChatConversation $conversation): bool
    {
        if ($conversation->type === ChatConversation::BUYER_SELLER) {
            return $conversation->buyer_id === $user->id
                || ($user->hasApprovedSellerAccess() && $conversation->store_id === $user->store?->id);
        }
        return $conversation->type === ChatConversation::SELLER_ADMIN
            && ($user->hasRole('admin') || ($user->hasApprovedSellerAccess() && $conversation->store_id === $user->store?->id));
    }

    public function send(User $sender, ChatConversation $conversation, string $body, string $token): ChatMessage
    {
        abort_unless($this->canAccess($sender, $conversation), 403);
        if (! $sender->is_active || $sender->trashed() || $sender->archived_at) abort(403);
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) throw ValidationException::withMessages(['body' => $body === '' ? 'Type a message before sending.' : 'Messages may not exceed 2,000 characters.']);
        return DB::transaction(function () use ($sender, $conversation, $body, $token) {
            $message = ChatMessage::firstOrCreate(['sender_id' => $sender->id, 'client_token' => $token], [
                'conversation_id' => $conversation->id, 'body' => $body,
            ]);
            if ($message->conversation_id !== $conversation->id) abort(409);
            $conversation->update(['last_message_at' => $message->created_at]);
            return $message;
        });
    }
}
