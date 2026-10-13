<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerWaybillTest extends TestCase
{
    use RefreshDatabase;

    private function seller(string $name): array
    {
        $user = User::factory()->create(['is_active'=>true]);
        $user->assignRole('seller');
        $profile = SellerProfile::create(['user_id'=>$user->id,'status'=>'approved']);
        $store = Store::create([
            'user_id'=>$user->id,'seller_profile_id'=>$profile->id,
            'name'=>$name,'slug'=>str($name)->slug().'-'.$user->id,
            'location'=>$name.' pickup address','status'=>'active',
        ]);

        return [$user,$store];
    }

    private function package(Store $store, string $status='ready_to_ship', string $paymentMethod='cod'): array
    {
        $buyer = User::factory()->create(['is_active'=>true]);
        $buyer->assignRole('buyer');
        $order = Order::create([
            'user_id'=>$buyer->id,'order_number'=>'SP-WB-'.$store->id.'-'.strtoupper(substr(uniqid(),-5)),
            'status'=>$status,'payment_method'=>$paymentMethod,'payment_status'=>$paymentMethod==='cod'?'cod':'paid',
            'subtotal'=>300,'shipping_fee'=>50,'total'=>350,
            'buyer_name'=>'Snapshot Recipient','buyer_phone'=>'09171234567',
            'shipping_address'=>[
                'full_name'=>'Snapshot Recipient','phone'=>'09171234567',
                'address_line'=>'12 Snapshot Street','barangay'=>'Sample Barangay',
                'city'=>'Quezon City','province'=>'Metro Manila','postal_code'=>'1100','country'=>'PH',
            ],
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id'=>$order->id,'store_id'=>$store->id,
            'seller_order_number'=>$order->order_number.'-S1','status'=>$status,
            'subtotal'=>300,'shipping_fee'=>50,'seller_total'=>270,'commission_amount'=>30,
        ]);
        $product = $this->addProductLine($order,$sellerOrder,$store,'Seller Item '.$store->id,3,'Color: Blue');
        $shipment = Shipment::create([
            'shipment_number'=>'SH-WB-'.$sellerOrder->id,'parcel_code'=>'PCL-WB-'.$sellerOrder->id,
            'seller_order_id'=>$sellerOrder->id,'order_id'=>$order->id,'store_id'=>$store->id,
            'status'=>'ready_for_pickup','pickup_address'=>['address'=>$store->location],
            'delivery_address'=>$order->shipping_address,'ready_at'=>now(),
        ]);

        return compact('buyer','order','sellerOrder','shipment','product');
    }

    private function addProductLine(
        Order $order,
        SellerOrder $sellerOrder,
        Store $store,
        string $name,
        int $quantity,
        ?string $variantLabel=null
    ): Product {
        $suffix = strtolower(substr(uniqid(),-6));
        $category = Category::create([
            'name'=>'Waybill Test '.$suffix,
            'slug'=>'waybill-test-'.$suffix,
        ]);
        $product = Product::create([
            'store_id'=>$store->id,'category_id'=>$category->id,'name'=>$name,
            'slug'=>'waybill-product-'.$suffix,'price'=>100,'stock'=>10,'sold_count'=>0,'is_active'=>true,
            'moderation_status'=>'clean','publication_status'=>'published',
        ]);
        OrderItem::create([
            'order_id'=>$order->id,'seller_order_id'=>$sellerOrder->id,'product_id'=>$product->id,
            'product_name'=>$product->name,'variant_label'=>$variantLabel,'sku'=>'SKU-'.$suffix,
            'price'=>100,'quantity'=>$quantity,'total'=>100*$quantity,
        ]);

        return $product;
    }

    private function addSellerShipment(Order $order, Store $store, string $suffix): array
    {
        $sellerOrder = SellerOrder::create([
            'order_id'=>$order->id,'store_id'=>$store->id,
            'seller_order_number'=>$order->order_number.'-'.$suffix,'status'=>'ready_to_ship',
            'subtotal'=>300,'shipping_fee'=>50,'seller_total'=>270,'commission_amount'=>30,
        ]);
        $product = $this->addProductLine($order,$sellerOrder,$store,'Seller Item '.$store->id,2,'Size: Medium');
        $shipment = Shipment::create([
            'shipment_number'=>'SH-WB-'.$sellerOrder->id,'parcel_code'=>'PCL-WB-'.$sellerOrder->id,
            'seller_order_id'=>$sellerOrder->id,'order_id'=>$order->id,'store_id'=>$store->id,
            'status'=>'ready_for_pickup','pickup_address'=>['address'=>$store->location],
            'delivery_address'=>$order->shipping_address,'ready_at'=>now(),
        ]);

        return compact('sellerOrder','shipment','product');
    }

    public function test_guest_and_buyer_cannot_access_seller_waybills(): void
    {
        $this->get(route('seller.waybills.index'))->assertRedirect(route('login'));

        $buyer = User::factory()->create(['is_active'=>true]);
        $buyer->assignRole('buyer');
        $this->actingAs($buyer)->get(route('seller.waybills.index'))->assertForbidden();
    }

    public function test_index_is_seller_scoped_searchable_filterable_and_paginated(): void
    {
        [$seller,$store] = $this->seller('Seller A');
        [, $otherStore] = $this->seller('Seller B');
        $own = $this->package($store);
        $foreign = $this->package($otherStore);

        $this->actingAs($seller)
            ->get(route('seller.waybills.index',['q'=>$own['shipment']->shipment_number,'status'=>'ready_for_pickup']))
            ->assertOk()
            ->assertSee($own['shipment']->shipment_number)
            ->assertDontSee($foreign['shipment']->shipment_number)
            ->assertSee('Generate Waybill');

        for ($index=0; $index<15; $index++) {
            $this->package($store);
        }
        $page = $this->get(route('seller.waybills.index'));
        $this->assertCount(15,$page->viewData('shipments'));
        $this->assertNotNull($page->viewData('shipments')->nextPageUrl());
        $this->assertStringContainsString('page=2',$page->viewData('shipments')->nextPageUrl());
    }

    public function test_index_handles_empty_orders_missing_shipment_unassigned_rider_and_generated_waybill(): void
    {
        [$seller,$store] = $this->seller('Seller A');
        $this->actingAs($seller)
            ->get(route('seller.waybills.index'))
            ->assertOk()
            ->assertSee('No waybills yet');

        $package = $this->package($store);
        $package['shipment']->delete();
        $this->get(route('seller.waybills.index'))
            ->assertOk()
            ->assertSee('No waybills yet');

        $shipment = Shipment::create([
            'shipment_number'=>'SH-WB-RESTORED-'.$package['sellerOrder']->id,
            'parcel_code'=>'PCL-WB-RESTORED-'.$package['sellerOrder']->id,
            'seller_order_id'=>$package['sellerOrder']->id,
            'order_id'=>$package['order']->id,
            'store_id'=>$store->id,
            'status'=>'ready_for_pickup',
            'pickup_address'=>['address'=>$store->location],
            'delivery_address'=>$package['order']->shipping_address,
            'ready_at'=>now(),
        ]);
        $this->get(route('seller.waybills.index'))
            ->assertOk()
            ->assertSee($shipment->shipment_number)
            ->assertSee('Ready');

        $shipment->update(['waybill_generated_at'=>now()]);
        $this->get(route('seller.waybills.index'))
            ->assertOk()
            ->assertSee($shipment->shipment_number)
            ->assertSee('Generated');
    }

    public function test_generation_is_idempotent_and_does_not_change_shipment_or_order_state(): void
    {
        [$seller,$store] = $this->seller('Seller A');
        $package = $this->package($store);
        $shipment = $package['shipment'];
        $originalNumber = $shipment->shipment_number;
        $this->travelTo(now()->addMinute());

        $this->actingAs($seller)
            ->get(route('seller.orders.show',$package['sellerOrder']))
            ->assertOk()
            ->assertSee(route('seller.waybills.generate',$package['sellerOrder']))
            ->assertSee('Generate Waybill');

        $this->post(route('seller.waybills.generate',$package['sellerOrder']))
            ->assertRedirect(route('seller.waybills.show',$shipment));
        $firstGeneratedAt = $shipment->fresh()->waybill_generated_at;
        $this->assertNotNull($firstGeneratedAt);
        $this->assertSame('ready_for_pickup',$shipment->fresh()->status);
        $this->assertSame('ready_to_ship',$package['sellerOrder']->fresh()->status);
        $this->assertSame(10,$package['product']->fresh()->stock);

        $this->travelTo(now()->addMinute());
        $this->get(route('seller.orders.show',$package['sellerOrder']))
            ->assertOk()->assertSee(route('seller.waybills.show',$shipment))->assertSee('View Waybill');
        $this->post(route('seller.waybills.generate',$package['sellerOrder']))
            ->assertRedirect(route('seller.waybills.show',$shipment));
        $this->assertSame($originalNumber,$shipment->fresh()->shipment_number);
        $this->assertTrue($firstGeneratedAt->equalTo($shipment->fresh()->waybill_generated_at));
        $this->assertSame('ready_for_pickup',$shipment->fresh()->status);
        $this->assertSame(10,$package['product']->fresh()->stock);
    }

    public function test_non_ready_order_cannot_generate_waybill(): void
    {
        [$seller,$store] = $this->seller('Seller A');
        $package = $this->package($store,'pending');

        $this->actingAs($seller)->post(route('seller.waybills.generate',$package['sellerOrder']))
            ->assertSessionHasErrors('waybill');
        $this->assertNull($package['shipment']->fresh()->waybill_generated_at);
        $this->assertSame('ready_for_pickup',$package['shipment']->fresh()->status);
    }

    public function test_another_sellers_waybill_cannot_be_viewed_printed_or_downloaded(): void
    {
        [$seller] = $this->seller('Seller A');
        [, $otherStore] = $this->seller('Seller B');
        $foreign = $this->package($otherStore);
        $foreign['shipment']->update(['waybill_generated_at'=>now()]);

        $this->actingAs($seller)
            ->get(route('seller.waybills.show',$foreign['shipment']))->assertNotFound();
        $this->get(route('seller.waybills.print',$foreign['shipment']))->assertNotFound();
        $this->get(route('seller.waybills.pdf',$foreign['shipment']))->assertNotFound();
        $this->post(route('seller.waybills.generate',$foreign['sellerOrder']))->assertNotFound();
    }

    public function test_waybill_uses_order_address_snapshot_and_only_seller_order_items(): void
    {
        [$seller,$store] = $this->seller('Seller A');
        [$otherSeller,$otherStore] = $this->seller('Seller B');
        $package = $this->package($store);
        $otherPackage = $this->addSellerShipment($package['order'],$otherStore,'S2');
        $package['order']->update(['total'=>700]);
        $package['shipment']->update(['waybill_generated_at'=>now()]);
        $otherPackage['shipment']->update(['waybill_generated_at'=>now()]);

        $page = $this->actingAs($seller)->get(route('seller.waybills.show',$package['shipment']))
            ->assertOk()
            ->assertSee('Snapshot Recipient')
            ->assertSee('12 Snapshot Street')
            ->assertSee('09171234567')
            ->assertSee('Seller Item '.$store->id)
            ->assertSee('Color: Blue')
            ->assertSee('3')
            ->assertDontSee('Seller Item '.$otherStore->id)
            ->assertSee('Cash on Delivery')
            ->assertDontSee('COD Amount')
            ->assertDontSee('₱700.00');
        $this->assertNotSame('Buyer Current Address',$page->viewData('deliveryAddress'));

        $this->actingAs($otherSeller)->get(route('seller.waybills.show',$otherPackage['shipment']))
            ->assertOk()
            ->assertSee('Seller Item '.$otherStore->id)
            ->assertDontSee('Seller Item '.$store->id)
            ->assertDontSee('COD Amount');
    }

    public function test_single_seller_checkout_waybill_includes_all_products_quantities_and_variants(): void
    {
        [$seller,$store] = $this->seller('Seller A');
        $package = $this->package($store);
        $this->addProductLine($package['order'],$package['sellerOrder'],$store,'Tumbler',1,'Color: Silver');
        $this->addProductLine($package['order'],$package['sellerOrder'],$store,'Mini Fan',3,'Color: White');
        OrderItem::create([
            'order_id'=>$package['order']->id,
            'seller_order_id'=>$package['sellerOrder']->id,
            'product_id'=>$package['product']->id,
            'product_name'=>'CloudComfort Chair',
            'variant_label'=>'Gray',
            'sku'=>'CHAIR-GRAY',
            'price'=>100,'quantity'=>2,'total'=>200,
        ]);
        OrderItem::create([
            'order_id'=>$package['order']->id,
            'seller_order_id'=>$package['sellerOrder']->id,
            'product_id'=>$package['product']->id,
            'product_name'=>'CloudComfort Chair',
            'variant_label'=>'Black',
            'sku'=>'CHAIR-BLACK',
            'price'=>100,'quantity'=>1,'total'=>100,
        ]);
        $package['shipment']->update(['waybill_generated_at'=>now()]);

        $response = $this->actingAs($seller)->get(route('seller.waybills.show',$package['shipment']))
            ->assertOk()
            ->assertSee('Seller Item '.$store->id)
            ->assertSee('Tumbler')
            ->assertSee('Mini Fan')
            ->assertSee('Color: Silver')
            ->assertSee('Color: White')
            ->assertSee('CloudComfort Chair')
            ->assertSee('Gray')
            ->assertSee('Black')
            ->assertSee('3')
            ->assertSee('2')
            ->assertSee('Cash on Delivery');

        $this->assertSame(2,substr_count($response->getContent(),'CloudComfort Chair'));
    }

    public function test_single_seller_cod_waybill_shows_the_parent_total_once(): void
    {
        [$seller,$store] = $this->seller('Seller A');
        $package = $this->package($store);
        $package['shipment']->update(['waybill_generated_at'=>now()]);

        $this->actingAs($seller)
            ->get(route('seller.waybills.show',$package['shipment']))
            ->assertOk()
            ->assertSee('Payment Method')
            ->assertSee('Cash on Delivery')
            ->assertSee('COD Amount')
            ->assertSee('₱350.00');
    }

    public function test_one_three_and_five_item_prints_keep_all_waybill_content_and_pdf_on_one_page(): void
    {
        [$seller,$store] = $this->seller('Seller A');
        $this->actingAs($seller);

        foreach ([1,3,5] as $itemCount) {
            $package = $this->package($store,'ready_to_ship','gcash');
            for ($line=2; $line<=$itemCount; $line++) {
                $this->addProductLine(
                    $package['order'],
                    $package['sellerOrder'],
                    $store,
                    'Print Product '.$itemCount.'-'.$line,
                    $line,
                    'Variant '.$line
                );
            }
            $package['shipment']->update(['waybill_generated_at'=>now()]);

            $print = $this->get(route('seller.waybills.print',$package['shipment']))
                ->assertOk()
                ->assertSee('SHOPPICK')
                ->assertSee('Ship From')
                ->assertSee('Ship To')
                ->assertSee('Payment Method')
                ->assertSee('Package Reference')
                ->assertSee('size:A4 landscape')
                ->assertSee('margin:8mm')
                ->assertSee('width:100%!important')
                ->assertSee('height:auto!important')
                ->assertSee('max-height:20mm')
                ->assertSee('<svg',false)
                ->assertSee('Seller Item '.$store->id);
            for ($line=2; $line<=$itemCount; $line++) {
                $print->assertSee('Print Product '.$itemCount.'-'.$line)
                    ->assertSee('Variant '.$line);
            }

            $pdf = $this->get(route('seller.waybills.pdf',$package['shipment']))->assertOk();
            preg_match('/\/Type\s*\/Pages\b[\s\S]*?\/Count\s+(\d+)/',$pdf->getContent(),$pdfPages);
            $this->assertSame('1',$pdfPages[1] ?? null,"{$itemCount}-item waybill PDF should fit one page.");
        }
    }

    public function test_prepaid_waybill_shows_payment_status_and_can_print_and_download_pdf(): void
    {
        [$seller,$store] = $this->seller('Seller A');
        $package = $this->package($store,'ready_to_ship','gcash');
        foreach ([
            ['Desk Lamp',1,'Color: Brass'],
            ['Notebook Set',2,'Page: Ruled'],
            ['Travel Mug',1,'Color: Forest Green'],
            ['USB-C Cable',3,'Length: 2 meters'],
        ] as [$name,$quantity,$variant]) {
            $this->addProductLine($package['order'],$package['sellerOrder'],$store,$name,$quantity,$variant);
        }
        $longAddress = 'Unit 18, Building 4, 123 Long Avenue, Barangay San Isidro, Quezon City, Metro Manila, 1100, Philippines';
        $address = $package['order']->shipping_address;
        $address['address_line'] = $longAddress;
        $package['order']->update(['shipping_address'=>$address]);
        $package['shipment']->update([
            'delivery_address'=>$address,
            'rider_id'=>null,
            'waybill_generated_at'=>now(),
        ]);
        $this->actingAs($seller);

        $show = $this->get(route('seller.waybills.show',$package['shipment']))
            ->assertOk()
            ->assertSee('Paid')
            ->assertSee('GCash')
            ->assertSee($longAddress)
            ->assertSee('Code 128 barcode for '.$package['shipment']->shipment_number)
            ->assertSee($package['shipment']->shipment_number)
            ->assertSee('Printed')
            ->assertSee('Desk Lamp')
            ->assertSee('Notebook Set')
            ->assertSee('Travel Mug')
            ->assertSee('USB-C Cable');
        $barcodeSvg = $show->viewData('barcodeSvg');
        $this->assertStringContainsString('<svg',$barcodeSvg);
        $this->assertStringNotContainsString('<!DOCTYPE',$barcodeSvg);

        $this->get(route('seller.waybills.print',$package['shipment']))
            ->assertOk()
            ->assertSee('window.print()')
            ->assertSee('Shipping Waybill')
            ->assertSee('size:A4 landscape')
            ->assertSee('Payment Method')
            ->assertSee('Package Reference')
            ->assertSee('Desk Lamp')
            ->assertSee('Notebook Set')
            ->assertSee('Travel Mug')
            ->assertSee('USB-C Cable')
            ->assertSee('<svg',false)
            ->assertSee($package['shipment']->shipment_number)
            ->assertViewHas('barcodeSvg',$barcodeSvg);
        $pdf = $this->get(route('seller.waybills.pdf',$package['shipment']))
            ->assertOk()
            ->assertHeader('Content-Type','application/pdf')
            ->assertHeader('Content-Disposition','attachment; filename="SHOPPICK_'.$package['shipment']->shipment_number.'_Waybill.pdf"');
        $this->assertStringStartsWith('%PDF',$pdf->getContent());
        preg_match('/\/Type\s*\/Pages\b[\s\S]*?\/Count\s+(\d+)/',$pdf->getContent(),$pdfPages);
        $this->assertSame('1',$pdfPages[1] ?? null);
        preg_match('/\/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/',$pdf->getContent(),$pageSize);
        $this->assertEqualsWithDelta(0,(float)($pageSize[1] ?? -1),0.1);
        $this->assertEqualsWithDelta(0,(float)($pageSize[2] ?? -1),0.1);
        $this->assertEqualsWithDelta(432,(float)($pageSize[3] ?? 0),0.1);
        $this->assertEqualsWithDelta(288,(float)($pageSize[4] ?? 0),0.1);
    }
}
