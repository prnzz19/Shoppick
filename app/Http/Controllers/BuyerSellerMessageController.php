<?php

namespace App\Http\Controllers;

use App\Models\BuyerSellerConversation;
use App\Models\BuyerSellerMessage;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Services\BuyerSellerMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuyerSellerMessageController extends Controller
{
    public function __construct(private BuyerSellerMessagingService $messaging) {}

    public function buyerIndex(Request $request): View
    {
        return $this->index($request, false);
    }

    public function sellerIndex(Request $request): View
    {
        return $this->index($request, true);
    }

    public function buyerShow(Request $request, int $conversation): View
    {
        return $this->show($request, $conversation, false);
    }

    public function sellerShow(Request $request, int $conversation): View
    {
        return $this->show($request, $conversation, true);
    }

    public function buyerStart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'seller_order_id' => ['nullable', 'integer', 'exists:seller_orders,id'],
        ]);

        if (empty($data['product_id']) === empty($data['seller_order_id'])) {
            return back()->withErrors(['context' => 'Choose one product or seller order to start a conversation.']);
        }

        $buyer = $request->user();
        if (! empty($data['product_id'])) {
            $product = Product::query()->publiclyVisible()->with('store')->findOrFail($data['product_id']);
            $store = $product->store;
            abort_unless($store, 404);
            $conversation = $this->messaging->start($buyer, $store, product: $product);
        } else {
            $sellerOrder = SellerOrder::query()
                ->whereHas('order', fn ($orders) => $orders->where('user_id', $buyer->id))
                ->with(['order', 'store'])
                ->findOrFail($data['seller_order_id']);
            $store = $sellerOrder->store;
            abort_unless($store, 404);
            $conversation = $this->messaging->start($buyer, $store, sellerOrder: $sellerOrder);
        }

        return redirect()->route('messages.show', $conversation);
    }

    public function sellerStart(Request $request, SellerOrder $sellerOrder): RedirectResponse
    {
        $store = $request->user()->store;
        abort_unless($store && (int) $sellerOrder->store_id === (int) $store->id, 404);

        $sellerOrder->load('order');
        $buyer = $sellerOrder->order?->user;
        abort_unless($buyer, 404);

        $conversation = $this->messaging->start(
            $buyer,
            $store,
            sellerOrder: $sellerOrder,
            sender: $request->user()
        );

        return redirect()->route('seller.messages.show', $conversation);
    }

    public function buyerSend(Request $request, int $conversation): JsonResponse|RedirectResponse
    {
        return $this->send($request, $conversation, false);
    }

    public function sellerSend(Request $request, int $conversation): JsonResponse|RedirectResponse
    {
        return $this->send($request, $conversation, true);
    }

    public function buyerUpdates(Request $request, int $conversation): JsonResponse
    {
        return $this->updates($request, $conversation, false);
    }

    public function sellerUpdates(Request $request, int $conversation): JsonResponse
    {
        return $this->updates($request, $conversation, true);
    }

    public function buyerOlder(Request $request, int $conversation): JsonResponse
    {
        return $this->older($request, $conversation, false);
    }

    public function sellerOlder(Request $request, int $conversation): JsonResponse
    {
        return $this->older($request, $conversation, true);
    }

    public function buyerAttachment(Request $request, int $conversation, int $attachment)
    {
        return $this->attachment($request, $conversation, $attachment, false);
    }

    public function sellerAttachment(Request $request, int $conversation, int $attachment)
    {
        return $this->attachment($request, $conversation, $attachment, true);
    }

    private function attachment(Request $request, int $id, int $attachment, bool $sellerView)
    {
        $conversation = $this->scopedConversation($request, $id, $sellerView)->firstOrFail();
        $file = \App\Models\BuyerSellerMessageAttachment::whereKey($attachment)
            ->whereHas('message', fn ($query) => $query->where('conversation_id', $conversation->id))->firstOrFail();
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($file->path), 404);
        return $disk->response($file->path, 'photo', [
            'Content-Type' => $file->mime_type, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'inline');
    }

    private function index(Request $request, bool $sellerView): View
    {
        $user = $request->user();
        $store = $sellerView ? $user->store : null;
        abort_if($sellerView && ! $store, 404);

        $search = trim((string) $request->query('q', ''));
        $query = BuyerSellerConversation::query()
            ->with([
                'buyer:id,name,avatar',
                'store:id,name,logo',
                'latestMessage.attachments',
                'latestMessage.sender:id,name',
                'latestMessage.product.images',
                'latestMessage.sellerOrder.order',
            ])
            ->withCount(['messages as unread_count' => fn ($messages) => $messages
                ->where('sender_id', '!=', $user->id)->whereNull('read_at')]);

        if ($sellerView) {
            $query->where('store_id', $store->id);
        } else {
            $query->where('buyer_id', $user->id);
        }

        if ($search !== '') {
            $query->where(function ($matches) use ($search, $sellerView) {
                if ($sellerView) {
                    $matches->whereHas('buyer', fn ($buyers) => $buyers->where('name', 'like', '%'.$search.'%'));
                } else {
                    $matches->whereHas('store', fn ($stores) => $stores->where('name', 'like', '%'.$search.'%'));
                }
                $matches->orWhereHas('messages.sellerOrder', fn ($orders) => $orders
                    ->where('seller_order_number', 'like', '%'.$search.'%')
                    ->orWhereHas('order', fn ($parentOrders) => $parentOrders->where('order_number', 'like', '%'.$search.'%')));
            });
        }

        $conversations = $query->orderByDesc('last_message_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view($sellerView ? 'seller.messages.index' : 'storefront.messages.index', [
            'conversations' => $conversations,
            'search' => $search,
            'activeConversation' => null,
            'messages' => collect(),
            'messagePayloads' => collect(),
            'hasMoreMessages' => false,
            'sellerView' => $sellerView,
        ]);
    }

    private function show(Request $request, int $id, bool $sellerView): View
    {
        $conversation = $this->scopedConversation($request, $id, $sellerView)
            ->with([
                'buyer:id,name,avatar',
                'store:id,user_id,name,logo',
            ])->firstOrFail();

        $this->messaging->markIncomingRead($conversation, $request->user());

        $recent = $conversation->messages()
            ->with([
                'attachments', 'sender:id,name',
                'product.images',
                'product.store.user',
                'product.store.sellerProfile',
                'sellerOrder.order',
                'conversation.store',
            ])
            ->latest('id')
            ->limit(41)
            ->get();
        $hasMoreMessages = $recent->count() > 40;
        $messages = $recent->take(40)->reverse()->values();
        $messagePayloads = $messages->mapWithKeys(fn ($message) => [
            $message->id => $this->messaging->messagePayload($message, $request->user(), $sellerView),
        ]);

        $conversations = $this->conversationList($request, $sellerView);

        return view($sellerView ? 'seller.messages.index' : 'storefront.messages.index', [
            'conversations' => $conversations,
            'search' => '',
            'activeConversation' => $conversation,
            'messages' => $messages,
            'messagePayloads' => $messagePayloads,
            'hasMoreMessages' => $hasMoreMessages,
            'sellerView' => $sellerView,
        ]);
    }

    private function send(Request $request, int $id, bool $sellerView): JsonResponse|RedirectResponse
    {
        $conversation = $this->scopedConversation($request, $id, $sellerView)->firstOrFail();
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120', 'dimensions:max_width=12000,max_height=12000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120', 'dimensions:max_width=12000,max_height=12000'],
            'client_token' => ['nullable', 'uuid'],
        ], [
            'photos.max' => 'You can attach up to 5 photos per message.',
        ]);
        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            $photos = [$photos];
        }
        if ($request->hasFile('photo')) {
            array_unshift($photos, $request->file('photo'));
        }
        if (count($photos) > 5) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'photos' => 'You can attach up to 5 photos per message.',
            ]);
        }
        $message = $this->messaging->send($conversation, $request->user(), $data['body'] ?? '', $photos, $data['client_token'] ?? null);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->messaging->messagePayload($message, $request->user(), $sellerView),
            ], 201);
        }

        return redirect()->route($sellerView ? 'seller.messages.show' : 'messages.show', $conversation);
    }

    private function updates(Request $request, int $id, bool $sellerView): JsonResponse
    {
        $data = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']]);
        $conversation = $this->scopedConversation($request, $id, $sellerView)->firstOrFail();
        $this->messaging->markIncomingRead($conversation, $request->user());

        $messages = $conversation->messages()
            ->with(['attachments', 'sender:id,name', 'product.images', 'product.store.user', 'product.store.sellerProfile',
                'sellerOrder.order', 'conversation.store'])
            ->where('id', '>', $data['after_id'] ?? 0)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn ($message) => $this->messaging->messagePayload($message, $request->user(), $sellerView));

        return response()->json(['messages' => $messages]);
    }

    private function older(Request $request, int $id, bool $sellerView): JsonResponse
    {
        $data = $request->validate(['before_id' => ['required', 'integer', 'min:1']]);
        $conversation = $this->scopedConversation($request, $id, $sellerView)->firstOrFail();
        $older = $conversation->messages()
            ->with(['attachments', 'sender:id,name', 'product.images', 'product.store.user', 'product.store.sellerProfile',
                'sellerOrder.order', 'conversation.store'])
            ->where('id', '<', $data['before_id'])
            ->latest('id')
            ->limit(41)
            ->get();

        $hasMore = $older->count() > 40;
        $messages = $older->take(40)->reverse()->values()
            ->map(fn ($message) => $this->messaging->messagePayload($message, $request->user(), $sellerView));

        return response()->json(['messages' => $messages, 'has_more' => $hasMore]);
    }

    private function conversationList(Request $request, bool $sellerView)
    {
        $user = $request->user();
        $query = BuyerSellerConversation::query()
            ->with(['buyer:id,name,avatar', 'store:id,name,logo', 'latestMessage.attachments'])
            ->withCount(['messages as unread_count' => fn ($messages) => $messages
                ->where('sender_id', '!=', $user->id)->whereNull('read_at')]);

        return ($sellerView ? $query->where('store_id', $user->store->id) : $query->where('buyer_id', $user->id))
            ->orderByDesc('last_message_at')->orderByDesc('id')->paginate(25);
    }

    private function scopedConversation(Request $request, int $id, bool $sellerView)
    {
        $query = BuyerSellerConversation::query()->whereKey($id);

        if ($sellerView) {
            $store = $request->user()->store;
            abort_if(! $store, 404);
            return $query->where('store_id', $store->id);
        }

        return $query->where('buyer_id', $request->user()->id);
    }
}
