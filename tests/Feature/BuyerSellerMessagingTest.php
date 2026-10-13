<?php

namespace Tests\Feature;

use App\Models\BuyerSellerConversation;
use App\Models\BuyerSellerMessage;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerSellerMessagingTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(string $name = 'Buyer A'): User
    {
        $buyer = User::factory()->create(['name' => $name, 'is_active' => true]);
        $buyer->assignRole('buyer');

        return $buyer;
    }

    private function seller(string $name): array
    {
        $seller = User::factory()->create(['name' => $name.' Owner', 'is_active' => true]);
        $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create([
            'user_id' => $seller->id,
            'seller_profile_id' => $profile->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.$seller->id,
            'status' => 'active',
        ]);

        return [$seller, $store];
    }

    private function product(Store $store, string $name): Product
    {
        $suffix = strtolower(substr(uniqid(), -7));
        $category = Category::create(['name' => 'Messaging '.$suffix, 'slug' => 'messaging-'.$suffix]);

        return Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => 'messaging-'.$suffix,
            'price' => 599,
            'stock' => 5,
            'is_active' => true,
            'moderation_status' => 'clean',
            'publication_status' => 'published',
        ]);
    }

    private function sellerOrder(User $buyer, Store $store, string $sellerOrderNumber, string $parentNumber): SellerOrder
    {
        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => $parentNumber,
            'status' => 'processing',
            'payment_method' => 'cod',
            'payment_status' => 'cod',
            'subtotal' => 1000,
            'shipping_fee' => 50,
            'total' => 1050,
            'buyer_name' => $buyer->name,
        ]);

        return SellerOrder::create([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'seller_order_number' => $sellerOrderNumber,
            'status' => 'processing',
            'subtotal' => 1000,
            'shipping_fee' => 50,
            'seller_total' => 950,
        ]);
    }

    public function test_buyer_starts_product_thread_seller_replies_and_opening_marks_messages_read(): void
    {
        $buyer = $this->buyer();
        [$seller, $store] = $this->seller('Panda Picks');
        $product = $this->product($store, 'Wireless Earbuds');

        $response = $this->actingAs($buyer)->post(route('messages.start'), ['product_id' => $product->id]);
        $conversation = BuyerSellerConversation::sole();
        $response->assertRedirect(route('messages.show', $conversation));
        $context = $conversation->messages()->sole();
        $this->assertSame('product', $context->context_type);
        $this->assertSame($buyer->id, $context->sender_id);

        $this->actingAs($seller)->get(route('seller.messages.index'))
            ->assertOk()
            ->assertSee('Buyer A')
            ->assertSee('Shared a product')
            ->assertSee('1');

        $sellerPage = $this->get(route('seller.messages.show', $conversation));
        $sellerPage
            ->assertOk()
            ->assertSee('Wireless Earbuds')
            ->assertSee('Product: Wireless Earbuds')
            ->assertSee('data-send-url="/seller/messages/'.$conversation->id.'/send"', false)
            ->assertSee('data-updates-url="/seller/messages/'.$conversation->id.'/updates"', false)
            ->assertSee('src="/js/buyer-seller-messages.js?v=', false)
            ->assertSee('name="csrf-token"', false);
        $this->assertNotNull($context->fresh()->read_at);

        $sellerMessage = $this->postJson(route('seller.messages.send', $conversation), [
            'body' => '<script>alert(1)</script>',
            'sender_id' => $buyer->id,
        ])->assertCreated()->assertJsonPath('message.sender_id', $seller->id);
        $message = BuyerSellerMessage::findOrFail($sellerMessage->json('message.id'));
        $this->assertSame($seller->id, $message->sender_id);
        $this->assertSame(1, BuyerSellerMessage::where('conversation_id', $conversation->id)
            ->where('body', '<script>alert(1)</script>')
            ->count());

        $buyerPage = $this->actingAs($buyer)->get(route('messages.show', $conversation));
        $buyerPage
            ->assertOk()
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('data-send-url="/messages/'.$conversation->id.'/send"', false)
            ->assertSee('data-updates-url="/messages/'.$conversation->id.'/updates"', false)
            ->assertSee('src="/js/buyer-seller-messages.js?v=', false)
            ->assertSee('name="csrf-token"', false);

        $this->getJson(route('messages.updates', [$conversation, 'after_id' => $context->id]))
            ->assertOk()
            ->assertJsonPath('messages.0.body', '<script>alert(1)</script>');
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_one_buyer_shop_pair_is_unique_and_repeated_product_starts_are_idempotent(): void
    {
        $buyer = $this->buyer();
        [, $store] = $this->seller('Shop One');
        $product = $this->product($store, 'Product One');
        $secondProduct = $this->product($store, 'Product Two');

        $this->actingAs($buyer)->post(route('messages.start'), ['product_id' => $product->id])->assertRedirect();
        $this->post(route('messages.start'), ['product_id' => $product->id])->assertRedirect();
        $this->post(route('messages.start'), ['product_id' => $secondProduct->id])->assertRedirect();

        $this->assertSame(1, BuyerSellerConversation::count());
        $this->assertSame(2, BuyerSellerMessage::where('context_type', 'product')->count());
        $this->assertSame(1, BuyerSellerMessage::where('context_key', 'product:'.$product->id)->count());

        $conversation = BuyerSellerConversation::firstOrFail();
        try {
            BuyerSellerConversation::create(['buyer_id' => $buyer->id, 'store_id' => $store->id]);
            $this->fail('The unique Buyer/Shop constraint should reject a duplicate conversation.');
        } catch (QueryException) {
            $this->assertSame(1, BuyerSellerConversation::count());
        }
    }

    public function test_dual_role_buyer_can_message_another_shop_but_not_their_own_shop(): void
    {
        $dualRole = $this->buyer('Dual Role Buyer');
        $dualRole->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $dualRole->id, 'status' => 'approved']);
        $ownStore = Store::create([
            'user_id' => $dualRole->id,
            'seller_profile_id' => $profile->id,
            'name' => 'Own Shop',
            'slug' => 'own-shop-'.$dualRole->id,
            'status' => 'active',
        ]);
        [, $otherStore] = $this->seller('Other Shop');
        $ownProduct = $this->product($ownStore, 'Own Item');
        $otherProduct = $this->product($otherStore, 'Other Item');

        $this->actingAs($dualRole)->post(route('messages.start'), ['product_id' => $otherProduct->id])->assertRedirect();
        $this->post(route('messages.start'), ['product_id' => $ownProduct->id])
            ->assertSessionHasErrors('store');

        $this->assertSame(1, BuyerSellerConversation::count());
        $this->get(route('messages.index'))->assertOk()->assertSee('Other Shop');
    }

    public function test_order_context_is_seller_scoped_for_multi_vendor_checkout(): void
    {
        $buyer = $this->buyer();
        [$sellerA, $storeA] = $this->seller('Seller A');
        [$sellerB, $storeB] = $this->seller('Seller B');
        $sellerOrderA = $this->sellerOrder($buyer, $storeA, 'SELLER-A-ORDER', 'PARENT-MULTI-1');
        $sellerOrderB = SellerOrder::create([
            'order_id' => $sellerOrderA->order_id,
            'store_id' => $storeB->id,
            'seller_order_number' => 'SELLER-B-ORDER',
            'status' => 'ready_to_ship',
            'subtotal' => 200,
            'seller_total' => 190,
        ]);
        $productA = $this->product($storeA, 'Private A Product');
        $productB = $this->product($storeB, 'Private B Product');
        foreach ([[$sellerOrderA, $productA], [$sellerOrderB, $productB]] as [$sellerOrder, $product]) {
            OrderItem::create([
                'order_id' => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 1,
                'price' => $product->price,
                'total' => $product->price,
            ]);
        }

        $this->actingAs($buyer)->post(route('messages.start'), ['seller_order_id' => $sellerOrderA->id])
            ->assertRedirect();
        $conversation = BuyerSellerConversation::sole();

        $this->actingAs($sellerA)->get(route('seller.messages.show', $conversation))
            ->assertOk()
            ->assertSee('SELLER-A-ORDER')
            ->assertDontSee('SELLER-B-ORDER')
            ->assertDontSee('Private B Product');
        $this->actingAs($sellerB)->get(route('seller.messages.show', $conversation))->assertNotFound();

        $this->actingAs($buyer)->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('PARENT-MULTI-1')
            ->assertDontSee('SELLER-B-ORDER')
            ->assertDontSee('Private B Product');
    }

    public function test_seller_can_start_the_same_buyer_thread_from_their_seller_order(): void
    {
        $buyer = $this->buyer();
        [$seller, $store] = $this->seller('Seller Shop');
        $sellerOrder = $this->sellerOrder($buyer, $store, 'SELLER-START-1', 'PARENT-START-1');

        $response = $this->actingAs($seller)->post(route('seller.messages.start', $sellerOrder));
        $conversation = BuyerSellerConversation::sole();
        $response->assertRedirect(route('seller.messages.show', $conversation));
        $this->assertSame($buyer->id, $conversation->buyer_id);
        $this->assertSame($store->id, $conversation->store_id);

        $this->actingAs($buyer)->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('PARENT-START-1');
    }

    public function test_nonparticipants_and_wrong_roles_cannot_access_conversations_or_seller_routes(): void
    {
        $buyerA = $this->buyer('Buyer A');
        $buyerB = $this->buyer('Buyer B');
        [$sellerA, $storeA] = $this->seller('Shop A');
        [$sellerB] = $this->seller('Shop B');
        $product = $this->product($storeA, 'Shop A Product');

        $this->actingAs($buyerA)->post(route('messages.start'), ['product_id' => $product->id])->assertRedirect();
        $conversation = BuyerSellerConversation::sole();

        $this->actingAs($buyerB)->get(route('messages.show', $conversation))->assertNotFound();
        $this->actingAs($sellerB)->get(route('seller.messages.show', $conversation))->assertNotFound();
        $this->actingAs($buyerA)->get(route('seller.messages.index'))->assertForbidden();
        $this->actingAs($sellerA)->get(route('messages.index'))->assertForbidden();
        $this->actingAs($buyerB)->postJson(route('seller.messages.send', $conversation), ['body' => 'spoof'])->assertForbidden();
    }

    public function test_empty_oversized_messages_and_missing_context_are_rejected(): void
    {
        $buyer = $this->buyer();
        [, $store] = $this->seller('Validation Shop');
        $product = $this->product($store, 'Validation Product');
        $this->actingAs($buyer)->post(route('messages.start'), ['product_id' => $product->id])->assertRedirect();
        $conversation = BuyerSellerConversation::sole();

        $this->postJson(route('messages.send', $conversation), ['body' => " \n "])->assertUnprocessable();
        $this->postJson(route('messages.send', $conversation), ['body' => str_repeat('x', 2001)])->assertUnprocessable();
        $this->post(route('messages.start'), [])->assertSessionHasErrors('context');
        $this->assertSame(1, BuyerSellerMessage::whereNotNull('context_type')->count());
    }

    public function test_conversation_search_latest_activity_order_and_buyer_unread_badge(): void
    {
        $buyer = $this->buyer();
        [$seller, $store] = $this->seller('Search Shop');
        $productA = $this->product($store, 'Search Product A');
        $productB = $this->product($store, 'Search Product B');
        $this->actingAs($buyer)->post(route('messages.start'), ['product_id' => $productA->id])->assertRedirect();
        $conversation = BuyerSellerConversation::sole();
        $this->postJson(route('messages.send', $conversation), ['body' => 'First message'])->assertCreated();
        $this->actingAs($seller)->postJson(route('seller.messages.send', $conversation), ['body' => 'Newest reply'])->assertCreated();

        $this->actingAs($buyer)->get(route('messages.index', ['q' => 'Search Shop']))
            ->assertOk()->assertSee('Search Shop')->assertSee('1');
        $this->get(route('messages.index', ['q' => 'No such shop']))->assertOk()->assertDontSee('Search Shop');
        $this->get(route('messages.show', $conversation))->assertOk()->assertSee('Newest reply');
    }

    public function test_deleted_product_context_does_not_break_historical_chat(): void
    {
        $buyer = $this->buyer();
        [$seller, $store] = $this->seller('Archived Shop');
        $product = $this->product($store, 'Archived Product');
        $this->actingAs($buyer)->post(route('messages.start'), ['product_id' => $product->id])->assertRedirect();
        $conversation = BuyerSellerConversation::sole();
        $product->delete();

        $this->actingAs($seller)->get(route('seller.messages.show', $conversation))
            ->assertOk()
            ->assertSee('Product unavailable');
    }

    public function test_message_pages_are_available_from_both_sidebars_and_existing_pages_keep_loading(): void
    {
        $buyer = $this->buyer();
        [$seller, $store] = $this->seller('Sidebar Shop');
        $product = $this->product($store, 'Sidebar Product');

        $this->actingAs($buyer)->get(route('messages.index'))->assertOk()->assertSee('Messages');
        $this->get(route('products.show', $product->slug))->assertOk()->assertSee('Message Seller');
        $this->get(route('seller.dashboard'))->assertForbidden();

        $this->actingAs($seller)->get(route('seller.messages.index'))->assertOk()->assertSee('Messages');
        $this->get(route('seller.dashboard'))->assertOk();
    }

    public function test_photos_both_directions_private_access_polling_read_preview_and_retry(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $buyer = $this->buyer();
        $otherBuyer = $this->buyer('Unrelated Buyer');
        [$seller, $store] = $this->seller('Photo Shop');
        [$otherSeller] = $this->seller('Unrelated Shop');
        $conversation = BuyerSellerConversation::create(['buyer_id' => $buyer->id, 'store_id' => $store->id]);
        foreach ([[$buyer, $seller, 'messages', 'seller.messages'], [$seller, $buyer, 'seller.messages', 'messages']] as [$sender, $receiver, $prefix, $peerPrefix]) {
            $token = (string) \Illuminate\Support\Str::uuid();
            $send = fn () => $this->actingAs($sender)->postJson(route($prefix.'.send', $conversation), [
                'photo' => \Illuminate\Http\UploadedFile::fake()->image('private.jpg'),
                'client_token' => $token, 'sender_id' => $otherBuyer->id,
            ])->assertCreated();
            $response = $send();
            $id = $response->json('message.id');
            $send()->assertJsonPath('message.id', $id);
            $message = BuyerSellerMessage::findOrFail($id);
            $this->assertSame($sender->id, $message->sender_id);
            $this->assertNull($message->body);
            $this->assertNull($message->read_at);
            $this->assertSame(1, $message->attachments()->count());
            $attachment = $message->attachments()->sole();
            \Illuminate\Support\Facades\Storage::disk('local')->assertExists($attachment->path);
            $this->assertStringNotContainsString('private.jpg', $attachment->path);
            $this->assertArrayNotHasKey('path', $response->json('message.attachments.0'));
            $url = route($peerPrefix.'.attachment', [$conversation, $attachment]);
            $this->actingAs($receiver)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->get(route($peerPrefix.'.index'))->assertOk()->assertSee('Photo');
            $this->assertSame(1, $conversation->messages()->where('sender_id', '!=', $receiver->id)->whereNull('read_at')->count());
            $this->getJson(route($peerPrefix.'.updates', [$conversation, 'after_id' => $id - 1]))
                ->assertOk()->assertJsonPath('messages.0.attachments.0.id', $attachment->id);
            $this->assertNotNull($message->fresh()->read_at);
            $this->get(route($peerPrefix.'.show', $conversation))->assertOk()->assertSee('data-message-photo', false)->assertSee('data-photo-preview', false);
            $this->actingAs($otherBuyer)->get(route('messages.attachment', [$conversation, $attachment]))->assertNotFound();
            $this->actingAs($otherSeller)->get(route('seller.messages.attachment', [$conversation, $attachment]))->assertNotFound();
            auth()->forgetGuards();
            $this->getJson(route('messages.attachment', [$conversation, $attachment]))->assertUnauthorized();
        }
        $this->assertSame(2, BuyerSellerMessage::count());
        $this->assertCount(2, \Illuminate\Support\Facades\Storage::disk('local')->allFiles());
        $this->actingAs($buyer)->postJson(route('messages.send', $conversation), [
            'body' => '<script>alert(1)</script>', 'photo' => \Illuminate\Http\UploadedFile::fake()->image('caption.png'),
        ])->assertCreated()->assertJsonPath('message.body', '<script>alert(1)</script>');
        $this->actingAs($seller)->get(route('seller.messages.show', $conversation))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        // Optional isolated HTML fixture for browser-only interaction/layout checks. No live data is seeded.
        if (getenv('CHAT_BROWSER_FIXTURE')) {
            file_put_contents(sys_get_temp_dir().'/shoppick-chat-seller.html', $this->get(route('seller.messages.show', $conversation))->getContent());
            file_put_contents(sys_get_temp_dir().'/shoppick-chat-buyer.html', $this->actingAs($buyer)->get(route('messages.show', $conversation))->getContent());
        }
    }

    public function test_photo_validation_rejects_unsafe_and_oversized_uploads_without_files_or_messages(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $buyer = $this->buyer();
        [, $store] = $this->seller('Validation Photos');
        $conversation = BuyerSellerConversation::create(['buyer_id' => $buyer->id, 'store_id' => $store->id]);
        $this->actingAs($buyer);
        foreach ([
            \Illuminate\Http\UploadedFile::fake()->createWithContent('bad.jpg', '<html><script>alert(1)</script></html>'),
            \Illuminate\Http\UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            \Illuminate\Http\UploadedFile::fake()->image('big.jpg')->size(5121),
            \Illuminate\Http\UploadedFile::fake()->create('archive.zip', 10, 'application/zip'),
        ] as $file) $this->postJson(route('messages.send', $conversation), ['photo' => $file])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->postJson(route('messages.send', $conversation), [])->assertUnprocessable();
        $this->assertSame(0, BuyerSellerMessage::count());
        $this->assertEmpty(\Illuminate\Support\Facades\Storage::disk('local')->allFiles());
    }

    public function test_one_message_accepts_up_to_five_photos_for_buyer_and_seller(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $buyer = $this->buyer();
        [$seller, $store] = $this->seller('Gallery Shop');
        $conversation = BuyerSellerConversation::create(['buyer_id' => $buyer->id, 'store_id' => $store->id]);
        $photos = collect(range(1, 5))->map(fn ($number) => \Illuminate\Http\UploadedFile::fake()->image('photo-'.$number.'.jpg'))->all();

        $buyerResponse = $this->actingAs($buyer)->postJson(route('messages.send', $conversation), [
            'body' => 'These are the photos.', 'photos' => $photos, 'client_token' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertCreated();
        $buyerMessage = BuyerSellerMessage::findOrFail($buyerResponse->json('message.id'));
        $this->assertSame(5, $buyerMessage->attachments()->count());
        $this->assertCount(5, $buyerResponse->json('message.attachments'));
        $this->assertSame('These are the photos.', $buyerResponse->json('message.body'));

        $sellerResponse = $this->actingAs($seller)->postJson(route('seller.messages.send', $conversation), [
            'photos' => [
                \Illuminate\Http\UploadedFile::fake()->image('reply-1.png'),
                \Illuminate\Http\UploadedFile::fake()->image('reply-2.png'),
            ],
        ])->assertCreated();
        $this->assertSame(2, BuyerSellerMessage::findOrFail($sellerResponse->json('message.id'))->attachments()->count());
        $this->assertSame(2, BuyerSellerMessage::where('conversation_id', $conversation->id)->count());
        $this->assertNull(BuyerSellerMessage::findOrFail($sellerResponse->json('message.id'))->read_at);

        $this->actingAs($buyer)->get(route('messages.show', $conversation))
            ->assertOk()->assertSee('2 photos')->assertSee('data-photo-input', false)->assertSee('>+</button>', false);

        $tooMany = collect(range(1, 6))->map(fn ($number) => \Illuminate\Http\UploadedFile::fake()->image('too-many-'.$number.'.jpg'))->all();
        $this->actingAs($buyer)->postJson(route('messages.send', $conversation), ['photos' => $tooMany])
            ->assertUnprocessable()->assertJsonValidationErrors('photos')
            ->assertJsonPath('errors.photos.0', 'You can attach up to 5 photos per message.');
        $this->assertSame(2, BuyerSellerMessage::where('conversation_id', $conversation->id)->count());
    }

    public function test_attachment_cannot_be_requested_through_a_different_conversation(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $buyer = $this->buyer();
        [$seller, $store] = $this->seller('Private Photo Shop');
        $conversation = BuyerSellerConversation::create(['buyer_id' => $buyer->id, 'store_id' => $store->id]);
        $other = BuyerSellerConversation::create(['buyer_id' => $this->buyer('Another Buyer')->id, 'store_id' => $store->id]);
        $response = $this->actingAs($buyer)->postJson(route('messages.send', $conversation), ['photo' => \Illuminate\Http\UploadedFile::fake()->image('photo.webp')])->assertCreated();
        $this->actingAs($seller)->get(route('seller.messages.attachment', [$other, $response->json('message.attachments.0.id')]))->assertNotFound();
    }

    public function test_failed_attachment_record_rolls_back_message_and_removes_uploaded_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $buyer = $this->buyer();
        [, $store] = $this->seller('Rollback Shop');
        $conversation = BuyerSellerConversation::create(['buyer_id' => $buyer->id, 'store_id' => $store->id]);
        \App\Models\BuyerSellerMessageAttachment::creating(fn () => throw new \RuntimeException('Simulated storage metadata failure'));
        try {
            app(\App\Services\BuyerSellerMessagingService::class)->send($conversation, $buyer, '', \Illuminate\Http\UploadedFile::fake()->image('photo.jpg'));
            $this->fail('Expected attachment record failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated storage metadata failure', $error->getMessage());
        } finally {
            \App\Models\BuyerSellerMessageAttachment::flushEventListeners();
        }
        $this->assertSame(0, BuyerSellerMessage::count());
        $this->assertEmpty(\Illuminate\Support\Facades\Storage::disk('local')->allFiles());
    }
}
