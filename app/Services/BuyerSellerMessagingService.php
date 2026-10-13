<?php

namespace App\Services;

use App\Models\BuyerSellerConversation;
use App\Models\BuyerSellerMessage;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerSellerMessagingService
{
    public function start(
        User $buyer,
        Store $store,
        ?Product $product = null,
        ?SellerOrder $sellerOrder = null,
        ?User $sender = null
    ): BuyerSellerConversation
    {
        if ((int) $store->user_id === (int) $buyer->id) {
            throw ValidationException::withMessages(['store' => 'You cannot start a conversation with your own shop.']);
        }

        $sender ??= $buyer;
        abort_unless(
            (int) $sender->id === (int) $buyer->id
            || (int) $sender->id === (int) $store->user_id,
            404
        );

        return DB::transaction(function () use ($buyer, $store, $product, $sellerOrder, $sender) {
            $conversation = BuyerSellerConversation::firstOrCreate([
                'buyer_id' => $buyer->id,
                'store_id' => $store->id,
            ]);

            if ($product) {
                abort_unless((int) $product->store_id === (int) $store->id, 404);
                $this->shareContext($conversation, $sender, 'product', $product);
            } elseif ($sellerOrder) {
                abort_unless(
                    (int) $sellerOrder->store_id === (int) $store->id
                    && (int) $sellerOrder->order?->user_id === (int) $buyer->id,
                    404
                );
                $this->shareContext($conversation, $sender, 'order', $sellerOrder);
            }

            return $conversation;
        });
    }

    public function send(BuyerSellerConversation $conversation, User $sender, string $body, array|\Illuminate\Http\UploadedFile|null $photos = [], ?string $clientToken = null): BuyerSellerMessage
    {
        $this->assertParticipant($conversation, $sender);
        $body = trim($body);
        $photos = $photos instanceof \Illuminate\Http\UploadedFile ? [$photos] : array_values(array_filter($photos ?? []));
        if ($body === '' && $photos === []) {
            throw ValidationException::withMessages(['body' => 'Write a message or choose a photo before sending.']);
        }
        if (count($photos) > 5) {
            throw ValidationException::withMessages(['photos' => 'You can attach up to 5 photos per message.']);
        }
        $paths = [];
        try {
            return DB::transaction(function () use ($conversation, $sender, $body, $photos, $clientToken, &$paths) {
                // Serialize sends for this conversation so retry tokens also cover concurrent requests.
                $conversation = BuyerSellerConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
                if ($clientToken && ($existing = $conversation->messages()->where('sender_id', $sender->id)->where('client_token', $clientToken)->first())) {
                    return $existing;
                }
                foreach ($photos as $photo) {
                    $path = $photo->store('message-attachments/conversation-'.$conversation->id, 'local');
                    if (! $path) {
                        throw new \RuntimeException('Photo storage failed. Please retry.');
                    }
                    $paths[] = [$path, $photo];
                }
                $message = $conversation->messages()->create([
                    'sender_id' => $sender->id, 'body' => $body === '' ? null : $body, 'client_token' => $clientToken,
                ]);
                foreach ($paths as [$path, $photo]) {
                    $message->attachments()->create([
                        'path' => $path, 'mime_type' => $photo->getMimeType(), 'file_size' => $photo->getSize(),
                    ]);
                }
                $conversation->update(['last_message_at' => $message->created_at]);
                return $message;
            });
        } catch (\Throwable $exception) {
            foreach ($paths as [$path]) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function markIncomingRead(BuyerSellerConversation $conversation, User $viewer): void
    {
        $this->assertParticipant($conversation, $viewer);
        $conversation->messages()
            ->where('sender_id', '!=', $viewer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function messagePayload(BuyerSellerMessage $message, User $viewer, bool $sellerView): array
    {
        $message->loadMissing([
            'attachments',
            'conversation.store',
            'sender:id,name',
            'product.images',
            'product.store.user',
            'product.store.sellerProfile',
            'sellerOrder.order',
        ]);
        $conversation = $message->conversation;
        $context = null;

        if ($message->context_type === 'product') {
            $product = $message->product;
            $validProduct = $product && (int) $product->store_id === (int) $conversation->store_id;
            $available = $validProduct && ! $product->trashed() && $product->is_active
                && $product->publication_status === 'published'
                && in_array($product->moderation_status, ['clean', 'approved'], true)
                && $product->store?->isMarketplaceActive();
            $image = $validProduct && ! $product->trashed() ? $product->main_image : null;

            $context = [
                'type' => 'product',
                'title' => $available ? $product->name : 'Product unavailable',
                'price' => $available ? number_format($product->salePrice(), 2) : null,
                'image' => $image ? asset('storage/'.$image) : null,
                'url' => $available ? route('products.show', $product->slug) : null,
            ];
        } elseif ($message->context_type === 'order') {
            $sellerOrder = $message->sellerOrder;
            $validOrder = $sellerOrder
                && (int) $sellerOrder->store_id === (int) $conversation->store_id
                && (int) $sellerOrder->order?->user_id === (int) $conversation->buyer_id;

            $context = [
                'type' => 'order',
                'title' => $validOrder
                    ? ($sellerView ? $sellerOrder->seller_order_number : $sellerOrder->order->order_number)
                    : 'Order unavailable',
                'status' => $validOrder ? str($sellerOrder->status)->replace('_', ' ')->title() : null,
                'url' => $validOrder
                    ? ($sellerView
                        ? route('seller.orders.show', $sellerOrder)
                        : route('orders.show', $sellerOrder->order->order_number))
                    : null,
            ];
        }

        return [
            'attachments' => $message->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
                'url' => rtrim(request()->getBaseUrl(), '/').route($sellerView ? 'seller.messages.attachment' : 'messages.attachment', [$conversation->id, $attachment->id], false),
            ])->values()->all(),
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name ?? 'SHOPPICK User',
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
            'time' => $message->created_at->isToday()
                ? $message->created_at->format('g:i A')
                : $message->created_at->format('M j, g:i A'),
            'context' => $context,
            'read_at' => $message->read_at?->toIso8601String(),
            'mine' => (int) $message->sender_id === (int) $viewer->id,
        ];
    }

    private function shareContext(
        BuyerSellerConversation $conversation,
        User $sender,
        string $type,
        Product|SellerOrder $context
    ): void {
        $key = $type === 'product' ? 'product:'.$context->id : 'seller-order:'.$context->id;
        $attributes = [
            'sender_id' => $sender->id,
            'body' => null,
            'context_type' => $type,
            'product_id' => $type === 'product' ? $context->id : null,
            'seller_order_id' => $type === 'order' ? $context->id : null,
        ];
        $message = $conversation->messages()->firstOrCreate(['context_key' => $key], $attributes);

        if ($message->wasRecentlyCreated) {
            $conversation->update(['last_message_at' => $message->created_at]);
        }
    }

    private function assertParticipant(BuyerSellerConversation $conversation, User $user): void
    {
        abort_unless(
            (int) $conversation->buyer_id === (int) $user->id
            || (int) $conversation->store?->user_id === (int) $user->id,
            404
        );
    }
}
