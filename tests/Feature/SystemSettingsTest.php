<?php

namespace Tests\Feature;

use App\Models\{User, SystemSetting, LogisticsSetting, Order};
use App\Services\{SystemSettings, NotificationService, OrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage, Artisan};
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = User::factory()->create(['is_active'=>true, 'registration_status'=>'approved']);
        $user->assignRole($role);
        return $user;
    }

    private function defaults(string $group): array
    {
        return collect(config("system-settings.groups.$group.fields"))->reject(fn ($field) => $field['type'] === 'file')
            ->map(fn ($field) => is_bool($field['default']) ? (int) $field['default'] : $field['default'])->all();
    }

    private function save(string $group, array $overrides = [])
    {
        return $this->put(route('admin.settings.update', $group), array_replace($this->defaults($group), $overrides));
    }

    public function test_admin_can_view_all_groups_and_save_valid_preferences_without_exposing_secrets(): void
    {
        $this->actingAs($this->account('admin'));
        foreach (array_keys(config('system-settings.groups')) as $group) {
            $this->get(route('admin.settings.index', ['group'=>$group]))->assertOk()->assertSee('System Settings')
                ->assertSee('aria-current="page"', false)->assertDontSee('DB_PASSWORD')->assertDontSee('APP_KEY');
            if ($group !== 'maintenance') {
                $this->save($group)->assertRedirect(route('admin.settings.index', ['group'=>$group]))->assertSessionHasNoErrors();
            }
        }
        $this->save('general', ['marketplace_name'=>'Example Marketplace','support_email'=>'support@example.test','DB_PASSWORD'=>'forbidden'])
            ->assertSessionHas('success','Settings updated successfully.');
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('Example Marketplace')->assertSee('support@example.test');
        $this->assertDatabaseMissing('system_settings',['key'=>'DB_PASSWORD']);
        $this->assertDatabaseHas('admin_activity_logs',['action'=>'system_settings.updated']);
        $this->save('logistics',['tracking_enabled'=>0])->assertSessionHasNoErrors();
        $this->assertFalse((bool) LogisticsSetting::valueFor('buyer_tracking_visibility'));
        $this->assertDatabaseMissing('system_settings',['key'=>'logistics.tracking_enabled']);
        $this->get(route('admin.settings.index',['group'=>'maintenance']))->assertSee(app()->version())->assertSee(PHP_VERSION);
    }

    public function test_invalid_values_and_disabling_required_approval_are_rejected(): void
    {
        $this->actingAs($this->account('admin'));
        $this->save('general',['support_email'=>'not-email','timezone'=>'wrong','currency'=>'NOPE'])
            ->assertSessionHasErrors(['support_email','timezone','currency'])
            ->assertRedirect(route('admin.settings.index',['group'=>'general']));
        $this->save('branding',['primary_color'=>'url(javascript:bad)'])->assertSessionHasErrors('primary_color');
        $this->save('seller',['require_approval'=>0,'logo_max_size'=>999999])->assertSessionHasErrors(['require_approval','logo_max_size']);
        $this->save('logistics',['require_rider_approval'=>0])->assertSessionHasErrors('require_rider_approval');
        $this->assertDatabaseCount('system_settings',0);
    }

    public function test_guests_and_other_roles_cannot_read_write_or_clear_settings(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('login'));
        foreach (['buyer','seller','rider','logistics'] as $role) {
            $this->actingAs($this->account($role));
            $this->get(route('admin.settings.index'))->assertForbidden();
            $this->save('marketplace')->assertForbidden();
            $this->post(route('admin.settings.cache'))->assertForbidden();
        }
        $this->assertDatabaseCount('system_settings',0);
    }

    public function test_branding_upload_replace_and_fallback_only_delete_feature_owned_files(): void
    {
        Storage::fake('public');
        $this->actingAs($this->account('admin'));
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7n8AAAAASUVORK5CYII=');
        $this->save('branding',['logo'=>UploadedFile::fake()->createWithContent('logo.png',$png)])->assertSessionHasNoErrors();
        $first = app(SystemSettings::class)->get('branding.logo');
        $this->assertStringStartsWith('system/',$first);
        Storage::disk('public')->assertExists($first);
        $this->save('branding',['logo'=>UploadedFile::fake()->createWithContent('new.png',$png)])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($first);
        $second = app(SystemSettings::class)->get('branding.logo');
        $this->get(route('admin.settings.index',['group'=>'branding']))->assertSee(Storage::disk('public')->url($second));
        $this->save('branding',['remove_logo'=>1])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($second);
        $this->get(route('admin.settings.index',['group'=>'branding']))->assertSee('Default SHOPPICK branding');
        Storage::disk('public')->put('stores/keep.png',$png);
        app(SystemSettings::class)->save('branding',['logo'=>'stores/keep.png']);
        $this->save('branding',['remove_logo'=>1])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists('stores/keep.png');
        $this->save('branding',['logo'=>UploadedFile::fake()->createWithContent('bad.svg','<svg onload="alert(1)"></svg>')])->assertSessionHasErrors('logo');
        $this->save('branding',['favicon'=>UploadedFile::fake()->create('large.png',600,'image/png')])->assertSessionHasErrors('favicon');
    }

    public function test_web_controls_and_maintenance_are_enforced_without_locking_out_admin(): void
    {
        $admin = $this->account('admin');
        $this->actingAs($admin);
        $this->save('marketplace',['allow_registration'=>0,'allow_seller_applications'=>0,'allow_reviews'=>0])->assertSessionHasNoErrors();
        $this->save('logistics',['rider_applications'=>0])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('register'))->assertForbidden();
        $this->post(route('register.submit'))->assertForbidden();
        $this->get(route('register.rider'))->assertForbidden();
        $buyer = $this->account('buyer');
        $this->actingAs($buyer)->post(route('seller.apply.store'))->assertForbidden();
        $this->post(route('review.store',['orderNumber'=>'test','productId'=>1]))->assertForbidden();
        $this->actingAs($admin);
        $this->save('seller',['applications_enabled'=>1])->assertSessionHasNoErrors();
        $this->assertTrue(app(SystemSettings::class)->get('marketplace.allow_seller_applications'));
        $this->save('marketplace',['status'=>'maintenance'])->assertSessionHasNoErrors();
        $this->get(route('admin.settings.index'))->assertOk();
        auth()->logout();
        $this->get('/')->assertStatus(503)->assertSee('back soon');
        $this->get(route('login'))->assertOk();
        $this->actingAs($admin);
        $this->save('marketplace',['status'=>'active'])->assertSessionHasNoErrors();
        $this->get('/')->assertOk();
    }

    public function test_cancellation_and_existing_notification_preferences_are_enforced(): void
    {
        $admin = $this->account('admin');
        $buyer = $this->account('buyer');
        $order = Order::create(['user_id'=>$buyer->id,'order_number'=>'SETTINGS-1','status'=>'pending','payment_method'=>'cod','payment_status'=>'cod','subtotal'=>100,'total'=>100,'buyer_name'=>$buyer->name,'buyer_phone'=>'09170000000','shipping_address'=>[]]);
        $this->actingAs($admin);
        $this->save('orders',['allow_cancellation'=>0])->assertSessionHasNoErrors();
        try { app(OrderService::class)->cancelOrder($order); $this->fail('Cancellation should be disabled.'); }
        catch (\Exception $e) { $this->assertSame('Order cancellation is currently disabled.',$e->getMessage()); }
        $order->forceFill(['created_at'=>now()->subHours(3)])->save();
        $this->save('orders',['cancellation_limit'=>1])->assertSessionHasNoErrors();
        try { app(OrderService::class)->cancelOrder($order); $this->fail('Cancellation deadline should apply.'); }
        catch (\Exception $e) { $this->assertSame('The order cancellation time limit has passed.',$e->getMessage()); }
        $this->save('notifications',['new_order'=>0,'order_status'=>0,'seller_application'=>0,'rider_application'=>0,'reports'=>0])->assertSessionHasNoErrors();
        foreach (['order','buyer_order_progress','seller_application','rider_application','moderation','report'] as $type) {
            $this->assertNull(NotificationService::send($buyer->id,'Test','Test',$type));
        }
        $this->assertNull(NotificationService::send($buyer->id,'Test','Test','order',null,['event'=>'new_order']));
        $this->assertNotNull(NotificationService::send($buyer->id,'Assignment','Required logistics alert','logistics'));
        $this->save('orders')->assertSessionHasNoErrors();
        app(OrderService::class)->cancelOrder($order);
        $this->assertSame('cancelled',$order->fresh()->status);
    }

    public function test_new_google_account_is_blocked_when_registration_is_disabled(): void
    {
        app(SystemSettings::class)->save('marketplace',['allow_registration'=>false]);
        $google = (new \Laravel\Socialite\Two\User)->setRaw(['verified_email'=>true])->map(['id'=>'settings-google','name'=>'New Buyer','email'=>'new@settings.test','avatar'=>null]);
        $provider = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($google);
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHas('authentication_error');
        $this->assertDatabaseMissing('users',['email'=>'new@settings.test']);
    }

    public function test_cache_action_only_calls_fixed_safe_command(): void
    {
        $this->actingAs($this->account('admin'));
        Artisan::shouldReceive('call')->once()->with('optimize:clear')->andReturn(0);
        $this->post(route('admin.settings.cache'),['command'=>'db:wipe'])->assertRedirect(route('admin.settings.index',['group'=>'maintenance']));
        $this->assertDatabaseHas('admin_activity_logs',['action'=>'system_settings.cache_cleared']);
    }

    public function test_seller_logo_requirement_and_limit_are_applied_to_application_validation(): void
    {
        $this->actingAs($this->account('admin'));
        $this->save('seller',['logo_required'=>1,'logo_max_size'=>64])->assertSessionHasNoErrors();
        $buyer=$this->account('buyer');
        $this->actingAs($buyer)->post(route('seller.apply.store'))->assertSessionHasErrors('logo');
        $this->get(route('seller.apply'))->assertOk()->assertSee('Shop Logo (Required)')->assertSee('Maximum 64 KB.');
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7n8AAAAASUVORK5CYII=');
        $this->post(route('seller.apply.store'),['logo'=>UploadedFile::fake()->createWithContent('large.png',$png.str_repeat(' ',70000))])->assertSessionHasErrors('logo');
        $this->assertDatabaseCount('seller_applications',0);
    }
}
