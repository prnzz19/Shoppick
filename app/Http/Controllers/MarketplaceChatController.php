<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Services\MarketplaceChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketplaceChatController extends Controller
{
    public function __construct(private MarketplaceChatService $chat) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $mode = $this->mode($request);
        $query = ChatConversation::query()->with(['buyer:id,name,avatar','store:id,user_id,name,slug,logo,status','latestMessage.sender:id,name'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $user->id)]);
        if ($mode === 'buyer') $query->where('type', ChatConversation::BUYER_SELLER)->where('buyer_id', $user->id);
        elseif ($mode === 'seller') $query->where('store_id', $user->store->id);
        else $query->where('type', ChatConversation::SELLER_ADMIN);
        $items = $query->orderByRaw('last_message_at IS NULL')->orderByDesc('last_message_at')->orderByDesc('id')->limit(100)->get();
        return response()->json(['conversations' => $items->map(fn ($c) => $this->conversation($c, $user, $mode)), 'unread' => $items->sum('unread_count')]);
    }

    public function startBuyer(Request $request)
    {
        $data = $request->validate(['store_id'=>['required','integer'],'product_id'=>['nullable','integer'],'seller_order_id'=>['nullable','integer']]);
        $store = Store::findOrFail($data['store_id']);
        $conversation = $this->chat->buyerConversation($request->user(), $store,
            isset($data['product_id']) ? Product::withTrashed()->findOrFail($data['product_id']) : null,
            isset($data['seller_order_id']) ? SellerOrder::findOrFail($data['seller_order_id']) : null);
        return response()->json(['conversation' => $this->conversation($conversation->load(['buyer','store','product.images','sellerOrder.order']), $request->user(), 'buyer')]);
    }

    public function startSupport(Request $request)
    {
        $conversation = $this->chat->supportConversation($request->user());
        return response()->json(['conversation' => $this->conversation($conversation->load(['store']), $request->user(), 'seller')]);
    }

    public function show(Request $request, ChatConversation $conversation)
    {
        abort_unless($this->chat->canAccess($request->user(), $conversation), 403);
        $before = $request->integer('before') ?: null;
        $messages = $conversation->messages()->with('sender:id,name')->when($before, fn ($q) => $q->where('id','<',$before))->latest('id')->limit(50)->get()->reverse()->values();
        $conversation->messages()->whereNull('read_at')->where('sender_id','!=',$request->user()->id)->update(['read_at'=>now()]);
        return response()->json([
            'conversation' => $this->conversation($conversation->load(['buyer','store','product.images','sellerOrder.order']), $request->user(), $this->mode($request)),
            'messages' => $messages->map(fn ($m) => ['id'=>$m->id,'body'=>$m->body,'mine'=>$m->sender_id===$request->user()->id,'sender'=>$m->sender?->name ?? 'Former user','read_at'=>$m->read_at?->toIso8601String(),'created_at'=>$m->created_at->toIso8601String()]),
            'has_older' => $messages->count() === 50,
        ]);
    }

    public function send(Request $request, ChatConversation $conversation)
    {
        $data = $request->validate(['body'=>['required','string','max:2000'],'client_token'=>['required','uuid']]);
        $message = $this->chat->send($request->user(), $conversation, $data['body'], $data['client_token']);
        return response()->json(['message'=>['id'=>$message->id,'body'=>$message->body,'mine'=>true,'sender'=>$request->user()->name,'read_at'=>$message->read_at?->toIso8601String(),'created_at'=>$message->created_at->toIso8601String()]], $message->wasRecentlyCreated ? 201 : 200);
    }

    private function mode(Request $request): string
    {
        if ($request->routeIs('admin.*')) { abort_unless($request->user()->hasRole('admin'), 403); return 'admin'; }
        if ($request->routeIs('seller.*')) { abort_unless($request->user()->hasApprovedSellerAccess(), 403); return 'seller'; }
        abort_unless($request->user()->hasRole('buyer'), 403); return 'buyer';
    }

    private function conversation(ChatConversation $c, $user, string $mode): array
    {
        $c->loadMissing(['buyer','store','product.images','sellerOrder.order','latestMessage']);
        $support = $c->type === ChatConversation::SELLER_ADMIN;
        $name = $support && $mode !== 'admin' ? 'SHOPPICK Support' : ($mode === 'seller' ? ($c->buyer?->name ?? 'Buyer') : $c->store?->name);
        return [
            'id'=>$c->id,'type'=>$c->type,'name'=>$name,'section'=>$support?'support':'customers','unread'=>(int)($c->unread_count ?? 0),
            'latest'=>$c->latestMessage?->body,'last_message_at'=>$c->last_message_at?->toIso8601String(),
            'context'=>$this->context($c, $mode),
        ];
    }

    private function context(ChatConversation $c, string $mode): ?array
    {
        if ($c->sellerOrder) return ['kind'=>'order','title'=>'Order '.$c->sellerOrder->seller_order_number,'subtitle'=>Str::headline($c->sellerOrder->status),'url'=>$mode==='seller'?route('seller.orders.show',$c->sellerOrder):route('orders.show',$c->sellerOrder->order?->order_number)];
        if ($c->product) return ['kind'=>'product','title'=>$c->product->name,'subtitle'=>$c->product->trashed()||!$c->product->is_active?'Product unavailable':'₱'.number_format($c->product->salePrice(),2),'url'=>$c->product->trashed()?null:route('products.show',$c->product->slug)];
        return null;
    }
}
