<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceChatTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $attributes=[]): User
    {
        $user=User::factory()->create(array_merge(['is_active'=>true,'registration_status'=>'approved'], $attributes));
        $user->assignRole($role);
        return $user;
    }

    private function shop(string $name='Panda Picks'): array
    {
        $seller=$this->user('seller',['name'=>$name.' Owner']);
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>$name,'slug'=>Str::slug($name).'-'.$seller->id,'status'=>'active']);
        return [$seller,$store];
    }

    public function test_buyer_and_seller_share_one_authorized_conversation_with_product_context(): void
    {
        $buyer=$this->user('buyer',['name'=>'Alden']); [$seller,$store]=$this->shop();
        $category=Category::create(['name'=>'Chat Products','slug'=>'chat-products','is_active'=>true]);
        $product=Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>'Wireless Earbuds','slug'=>'wireless-earbuds-chat','is_active'=>true,'publication_status'=>'published','moderation_status'=>'clean','price'=>599,'stock'=>5]);
        $first=$this->actingAs($buyer)->postJson(route('chat.buyer.start'),['store_id'=>$store->id,'product_id'=>$product->id])->assertOk()->json('conversation.id');
        $second=$this->postJson(route('chat.buyer.start'),['store_id'=>$store->id,'product_id'=>$product->id])->assertOk()->json('conversation.id');
        $this->assertSame($first,$second); $this->assertDatabaseCount('chat_conversations',1);
        $this->postJson(route('chat.messages.store',$first),['body'=>'  Hello, is this available?  ','client_token'=>(string)Str::uuid()])->assertCreated();
        $this->actingAs($seller)->getJson(route('seller.chat.index'))->assertOk()->assertJsonPath('conversations.0.name','Alden')->assertJsonPath('conversations.0.unread',1);
        $this->getJson(route('seller.chat.show',$first))->assertOk()->assertJsonPath('messages.0.body','Hello, is this available?');
        $this->postJson(route('seller.chat.messages.store',$first),['body'=>'Yes, it is available.','client_token'=>(string)Str::uuid()])->assertCreated();
        $this->actingAs($buyer)->getJson(route('chat.show',$first))->assertOk()->assertJsonCount(2,'messages')->assertJsonPath('conversation.context.kind','product');
    }

    public function test_participant_idor_and_sender_spoofing_are_blocked(): void
    {
        $buyer=$this->user('buyer'); $otherBuyer=$this->user('buyer'); [$seller,$store]=$this->shop(); [$otherSeller]=$this->shop('Other Shop');
        $id=$this->actingAs($buyer)->postJson(route('chat.buyer.start'),['store_id'=>$store->id])->json('conversation.id');
        $payload=['body'=>'Spoof attempt','client_token'=>(string)Str::uuid(),'sender_id'=>$seller->id,'buyer_id'=>$buyer->id];
        $this->actingAs($otherBuyer)->getJson(route('chat.show',$id))->assertForbidden();
        $this->actingAs($otherSeller)->getJson(route('seller.chat.show',$id))->assertForbidden();
        $this->actingAs($buyer)->postJson(route('chat.messages.store',$id),$payload)->assertCreated();
        $this->assertDatabaseHas('chat_messages',['sender_id'=>$buyer->id,'body'=>'Spoof attempt']);
    }

    public function test_multi_vendor_order_context_is_seller_scoped(): void
    {
        $buyer=$this->user('buyer'); [, $storeA]=$this->shop('Alpha Shop'); [, $storeB]=$this->shop('Beta Shop');
        $order=Order::create(['user_id'=>$buyer->id,'order_number'=>'SP-CHAT-1','status'=>'pending','payment_method'=>'cod','payment_status'=>'unpaid']);
        $a=SellerOrder::create(['order_id'=>$order->id,'store_id'=>$storeA->id,'seller_order_number'=>'SP-A','status'=>'pending']);
        $b=SellerOrder::create(['order_id'=>$order->id,'store_id'=>$storeB->id,'seller_order_number'=>'SP-B','status'=>'pending']);
        $id=$this->actingAs($buyer)->postJson(route('chat.buyer.start'),['store_id'=>$storeA->id,'seller_order_id'=>$a->id])->assertOk()->json('conversation.id');
        $this->postJson(route('chat.buyer.start'),['store_id'=>$storeA->id,'seller_order_id'=>$b->id])->assertNotFound();
        $this->assertSame($a->id,ChatConversation::find($id)->seller_order_id);
    }

    public function test_dual_role_uses_buyer_identity_and_cannot_chat_with_own_shop(): void
    {
        [$dual,$ownStore]=$this->shop('Own Shop'); $dual->assignRole('buyer'); [, $otherStore]=$this->shop('Other Shop');
        $this->actingAs($dual)->postJson(route('chat.buyer.start'),['store_id'=>$ownStore->id])->assertStatus(422);
        $id=$this->postJson(route('chat.buyer.start'),['store_id'=>$otherStore->id])->assertOk()->json('conversation.id');
        $this->assertDatabaseHas('chat_conversations',['id'=>$id,'buyer_id'=>$dual->id,'store_id'=>$otherStore->id,'type'=>'buyer_seller']);
    }

    public function test_seller_admin_support_is_unique_and_only_admin_can_reply_through_admin_routes(): void
    {
        [$seller,$store]=$this->shop(); [$otherSeller]=$this->shop('Other Shop'); $buyer=$this->user('buyer'); $admin=$this->user('admin',['name'=>'Support Admin']);
        $id=$this->actingAs($seller)->postJson(route('seller.chat.support'))->assertOk()->json('conversation.id');
        $this->postJson(route('seller.chat.support'))->assertOk()->assertJsonPath('conversation.id',$id);
        $this->postJson(route('seller.chat.messages.store',$id),['body'=>'I need help with my product listing.','client_token'=>(string)Str::uuid()])->assertCreated();
        $this->actingAs($admin)->getJson(route('admin.seller-support.conversations'))->assertOk()->assertJsonPath('conversations.0.name',$store->name)->assertJsonPath('conversations.0.unread',1);
        $this->postJson(route('admin.seller-support.messages.store',$id),['body'=>"We'll review it.",'client_token'=>(string)Str::uuid()])->assertCreated();
        $this->actingAs($otherSeller)->getJson(route('seller.chat.show',$id))->assertForbidden();
        $this->actingAs($buyer)->getJson(route('chat.show',$id))->assertForbidden();
        $this->actingAs($seller)->postJson(route('admin.seller-support.messages.store',$id),['body'=>'fake','client_token'=>(string)Str::uuid()])->assertForbidden();
    }

    public function test_validation_xss_unread_sort_read_and_idempotent_send(): void
    {
        $buyer=$this->user('buyer'); [$seller,$store]=$this->shop(); $id=$this->actingAs($buyer)->postJson(route('chat.buyer.start'),['store_id'=>$store->id])->json('conversation.id');
        $this->postJson(route('chat.messages.store',$id),['body'=>'   ','client_token'=>(string)Str::uuid()])->assertUnprocessable();
        $this->postJson(route('chat.messages.store',$id),['body'=>str_repeat('x',2001),'client_token'=>(string)Str::uuid()])->assertUnprocessable();
        $token=(string)Str::uuid(); $body='<script>alert(1)</script>';
        $this->postJson(route('chat.messages.store',$id),compact('body')+['client_token'=>$token])->assertCreated();
        $this->postJson(route('chat.messages.store',$id),compact('body')+['client_token'=>$token])->assertOk();
        $this->assertSame(1,ChatMessage::count());
        $this->actingAs($seller)->getJson(route('seller.chat.show',$id))->assertOk()->assertJsonPath('messages.0.body',$body);
        $this->assertNotNull(ChatMessage::first()->fresh()->read_at);
        $this->assertStringNotContainsString('<script>',view('components.marketplace-chat',['mode'=>'seller','embedded'=>false])->render());
        $this->assertDatabaseCount('chat_messages',1);
    }
}
