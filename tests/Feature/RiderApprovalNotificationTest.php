<?php

namespace Tests\Feature;

use App\Mail\RiderApplicationApproved;
use App\Models\{LogisticsHub, LogisticsProvider, Permission, RiderProfile, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Storage};
use Tests\TestCase;

class RiderApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private int $providerSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer', 'seller', 'admin', 'logistics', 'rider'] as $slug) {
            Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        }
        $permission = Permission::create(['name' => 'Manage riders', 'slug' => 'manage_riders', 'guard_name' => 'web', 'group' => 'Logistics']);
        Role::where('slug', 'logistics')->first()->permissions()->attach($permission);
        Storage::fake('local');
        Mail::fake();
    }

    private function provider(): array
    {
        $this->providerSequence++;
        $manager = User::factory()->create([
            'name' => 'SHOPPICK Logistics Manager '.$this->providerSequence,
            'email' => 'manager'.$this->providerSequence.'@example.test',
            'is_active' => true, 'registration_type' => 'logistics', 'registration_status' => 'approved',
        ])->assignRole('logistics');
        $provider = LogisticsProvider::create([
            'owner_user_id' => $manager->id, 'company_name' => 'SHOPPICK Logistics '.$this->providerSequence,
            'status' => 'approved', 'is_active' => true,
        ]);
        $provider->users()->attach($manager->id, ['membership_role' => 'owner', 'is_active' => true]);
        $hub = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'SP-HUB-'.$this->providerSequence, 'name' => 'SHOPPICK Hub '.$this->providerSequence, 'address' => 'Manila', 'status' => 'active']);
        return [$manager, $provider, $hub];
    }

    private function application(LogisticsProvider $provider, bool $verified = true, string $suffix = '001'): RiderProfile
    {
        $rider = User::factory()->create([
            'name' => 'Pedro Garcia', 'first_name' => 'Pedro Garcia', 'last_name' => 'Garcia',
            'email' => 'pedro'.$suffix.'@example.test', 'email_verified_at' => $verified ? now() : null,
            'is_active' => false, 'registration_type' => 'rider', 'registration_status' => 'pending',
            'valid_id_path' => 'rider-documents/valid-ids/id-'.$suffix.'.pdf',
        ])->assignRole('rider');
        foreach (['rider-documents/licenses/front-'.$suffix.'.pdf', 'rider-documents/licenses/back-'.$suffix.'.pdf', 'rider-documents/proof/address-'.$suffix.'.pdf', 'rider-documents/vehicles/orcr-'.$suffix.'.pdf'] as $path) {
            Storage::disk('local')->put($path, 'private');
        }
        return RiderProfile::create([
            'user_id' => $rider->id, 'logistics_provider_id' => $provider->id, 'logistics_user_id' => $provider->owner_user_id,
            'account_status' => 'inactive', 'availability' => 'unavailable', 'requested_service_area' => 'Manila', 'requested_rider_type' => 'both',
            'driver_license_number' => 'DL-'.$rider->id, 'driver_license_expires_at' => now()->addYear(),
            'driver_license_front_path' => 'rider-documents/licenses/front-'.$suffix.'.pdf', 'driver_license_back_path' => 'rider-documents/licenses/back-'.$suffix.'.pdf',
            'driver_license_status' => 'pending_review', 'proof_of_address_path' => 'rider-documents/proof/address-'.$suffix.'.pdf', 'or_cr_path' => 'rider-documents/vehicles/orcr-'.$suffix.'.pdf',
        ]);
    }

    private function approve(User $manager, RiderProfile $rider, LogisticsHub $hub)
    {
        return $this->actingAs($manager)->post(route('logistics.riders.application.review', $rider), [
            'decision' => 'approved', 'approved_service_area' => 'Metro Manila', 'hub_id' => $hub->id, 'rider_type' => 'both',
        ]);
    }

    public function test_verified_rider_approval_queues_one_branded_email_after_commit(): void
    {
        [$manager, $provider, $hub] = $this->provider();
        $rider = $this->application($provider);

        $this->approve($manager, $rider, $hub)->assertSessionHasNoErrors();

        Mail::assertQueued(RiderApplicationApproved::class, function (RiderApplicationApproved $mail) use ($provider) {
            return $mail->riderName === 'Pedro Garcia'
                && $mail->providerName === $provider->company_name
                && str_ends_with($mail->loginUrl, '/rider/login');
        });
        Mail::assertQueuedCount(1);
        $rendered = (new RiderApplicationApproved('Pedro Garcia', $provider->company_name, 'https://shoppickmarket.shop/rider/login'))->render();
        $this->assertStringContainsString('Pedro Garcia', $rendered);
        $this->assertStringContainsString($provider->company_name, $rendered);
        $this->assertStringContainsString('https://shoppickmarket.shop/rider/login', $rendered);
        $this->assertDatabaseHas('users', ['id' => $rider->user_id, 'registration_status' => 'approved', 'is_active' => true]);
    }

    public function test_repeated_approval_and_non_approved_decisions_do_not_queue_approval_email(): void
    {
        [$manager, $provider, $hub] = $this->provider();
        $rider = $this->application($provider);
        $this->approve($manager, $rider, $hub)->assertSessionHasNoErrors();
        $this->approve($manager, $rider, $hub)->assertStatus(422);
        Mail::assertQueuedCount(1);

        Mail::fake();
        $second = $this->application($provider, true, '002');
        $this->actingAs($manager)->post(route('logistics.riders.application.review', $second), ['decision' => 'rejected', 'reason' => 'Incomplete documents'])->assertSessionHasNoErrors();
        Mail::assertNothingQueued();
    }

    public function test_unverified_approved_rider_keeps_approval_but_email_is_skipped_safely(): void
    {
        [$manager, $provider, $hub] = $this->provider();
        $rider = $this->application($provider, false, '003');
        $this->approve($manager, $rider, $hub)->assertSessionHasNoErrors();
        Mail::assertNothingQueued();
        $this->assertDatabaseHas('users', ['id' => $rider->user_id, 'registration_status' => 'approved', 'is_active' => true]);
    }

    public function test_cross_provider_approval_cannot_queue_notification(): void
    {
        [$manager, $provider] = $this->provider();
        [, $otherProvider, $otherHub] = $this->provider();
        $rider = $this->application($otherProvider, true, '004');
        $this->actingAs($manager)->post(route('logistics.riders.application.review', $rider), ['decision' => 'approved', 'approved_service_area' => 'Metro Manila', 'hub_id' => $otherHub->id, 'rider_type' => 'both'])->assertNotFound();
        Mail::assertNothingQueued();
        $this->assertSame('pending', $rider->user->fresh()->registration_status);
    }
}
