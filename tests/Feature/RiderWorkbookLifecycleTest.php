<?php

namespace Tests\Feature;

use App\Mail\RiderActivationInvitation;
use App\Models\{Permission, Role, RiderProfile, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Hash, Mail, Storage};
use Tests\TestCase;

class RiderWorkbookLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        foreach (['buyer', 'seller', 'admin', 'logistics', 'rider'] as $slug) {
            Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        }
        $permission = Permission::create(['name' => 'manage_riders', 'slug' => 'manage_riders', 'guard_name' => 'web', 'group' => 'Logistics']);
        Role::where('slug', 'logistics')->first()->permissions()->attach($permission);
        Storage::fake('local');
        Mail::fake();
    }

    private function logistics(string $name = 'Bilis Hatid Express Inc.', string $email = 'operations@bilishatid.example.com', string $status = 'approved'): User
    {
        return User::factory()->create(['name' => $name, 'email' => $email, 'is_active' => true, 'registration_type' => 'logistics', 'registration_status' => $status])->assignRole('logistics');
    }

    private function payload(string $suffix = '001'): array
    {
        return [
            'first_name' => 'Pedro Jose', 'last_name' => 'Garcia', 'birthday' => '1995-03-22',
            'email' => "pedrojose{$suffix}@example.com", 'phone' => '0945123'.str_pad($suffix, 4, '0', STR_PAD_LEFT),
            'driver_license_number' => "N01-23-{$suffix}", 'driver_license_expires_at' => '2030-03-22',
            'vehicle_type' => 'Motorcycle', 'plate_number' => "12{$suffix}ABC",
            'driver_license' => UploadedFile::fake()->create('drivers_license.jpg', 1500, 'image/jpeg'),
            'or_cr' => UploadedFile::fake()->create('or_cr.pdf', 900, 'application/pdf'),
        ];
    }

    private function createdRider(User $owner, string $suffix = '001'): RiderProfile
    {
        $this->actingAs($owner)->post(route('logistics.riders.store'), $this->payload($suffix))->assertSessionHasNoErrors();
        return RiderProfile::where('logistics_user_id', $owner->id)->latest('id')->firstOrFail();
    }

    private function emailedToken(): string
    {
        $mail = Mail::sent(RiderActivationInvitation::class)->last();
        $this->assertNotNull($mail);
        return basename((string) parse_url($mail->activationUrl, PHP_URL_PATH));
    }

    public function test_tc_rid_001_and_019_create_only_one_pending_rider_and_send_one_invitation(): void
    {
        $owner = $this->logistics();
        $otherOwner = $this->logistics('Untrusted Provider', 'untrusted@example.test');
        $data = $this->payload('019');
        $data['logistics_user_id'] = $otherOwner->id;
        $this->actingAs($owner)->post(route('logistics.riders.store'), $data)->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('logistics.riders.store'), $data)->assertSessionHasErrors('email');
        $this->actingAs($owner)->post(route('logistics.riders.store'), $data)->assertSessionHasErrors('email');

        $profile = RiderProfile::where('logistics_user_id', $owner->id)->firstOrFail();
        $this->assertSame('pending_activation', $profile->account_status);
        $this->assertSame('unavailable', $profile->availability);
        $this->assertFalse($profile->isAssignmentEligible());
        $this->assertFalse($profile->user->is_active);
        $this->assertSame([$owner->id], [$profile->logistics_user_id]);
        $this->assertSame(['rider'], $profile->user->roles()->pluck('slug')->all());
        $this->assertSame($owner->name, $profile->logisticsOwner->name);
        $this->assertDatabaseHas('rider_activation_tokens', ['rider_profile_id' => $profile->id]);
        $this->assertSame(1, RiderProfile::where('user_id', $profile->user_id)->count());
        Mail::assertSentTimes(RiderActivationInvitation::class, 1);
        $expiry = \App\Models\RiderActivationToken::where('rider_profile_id', $profile->id)->firstOrFail()->expires_at;
        $this->assertSame(86400, $expiry->timestamp - now()->timestamp); // TC-RID-023/A13 workbook rule.
    }

    public function test_a3_email_identity_is_trimmed_case_insensitive_and_unique_across_roles(): void
    {
        $owner = $this->logistics();
        User::factory()->create(['email' => 'Already.Used@example.test'])->assignRole('buyer');
        $data = $this->payload('003');
        $data['email'] = '  already.used@EXAMPLE.test  ';
        $this->actingAs($owner)->post(route('logistics.riders.store'), $data)->assertSessionHasErrors('email');
        $deleted = User::factory()->create(['email' => 'deleted.identity@example.test']);
        $deleted->delete();
        $deletedData = $this->payload('004');
        $deletedData['email'] = 'DELETED.IDENTITY@EXAMPLE.TEST';
        $this->post(route('logistics.riders.store'), $deletedData)->assertSessionHasErrors('email');
        $this->assertSame(0, RiderProfile::count());
        Mail::assertNothingOutgoing();
    }

    public function test_tc_rid_020_021_022_023_025_activation_rules_are_enforced(): void
    {
        $owner = $this->logistics();
        $profile = $this->createdRider($owner, '020');
        $token = $this->emailedToken();
        $this->post(route('logout'))->assertRedirect();

        $this->get(route('rider.activation.show', $token))->assertOk()->assertSee('Activate your Rider account');
        $this->post(route('rider.activation.complete', $token), ['password' => 'Ride@26', 'password_confirmation' => 'Ride@26'])->assertSessionHasErrors('password');
        foreach (['rider@2026', 'RIDER@2026', 'Rider@abcd', 'Rider2026'] as $invalidPassword) {
            $this->post(route('rider.activation.complete', $token), ['password' => $invalidPassword, 'password_confirmation' => $invalidPassword])->assertSessionHasErrors('password');
        }
        $this->post(route('rider.activation.complete', $token), ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2027'])->assertSessionHasErrors('password');
        $this->assertFalse($profile->user->fresh()->is_active);
        $this->assertSame('pending_activation', $profile->fresh()->account_status);

        $this->travel(24)->hours();
        $this->travel(1)->minutes();
        $this->get(route('rider.activation.show', $token))->assertOk()->assertSee('activation link has expired');
        $this->post(route('rider.activation.complete', $token), ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026'])->assertSessionHasErrors('token');
        $this->assertFalse($profile->user->fresh()->is_active);
    }

    public function test_tc_rid_020_026_027_activation_enables_rider_login_and_provider_display(): void
    {
        $owner = $this->logistics();
        $profile = $this->createdRider($owner, '027');
        $token = $this->emailedToken();
        $this->post(route('logout'))->assertRedirect();
        $credentials = ['email' => $profile->user->email, 'password' => 'Rider@2026'];
        $this->post(route('rider.login.submit'), $credentials)->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('rider.activation.complete', $token), ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026'])->assertRedirect(route('rider.login'));
        $this->assertTrue($profile->user->fresh()->is_active);
        $this->assertSame('active', $profile->fresh()->account_status);
        $this->assertTrue(Hash::check('Rider@2026', $profile->user->fresh()->password));
        $this->assertDatabaseMissing('rider_activation_tokens', ['rider_profile_id' => $profile->id]);

        $this->post(route('rider.activation.complete', $token), ['password' => 'Other@2026', 'password_confirmation' => 'Other@2026'])->assertSessionHasErrors('token');
        $this->post(route('rider.login.submit'), $credentials)->assertRedirect(route('rider.dashboard'));
        $this->get(route('rider.dashboard'))->assertOk()->assertSee($owner->name)->assertSee('Logistics Provider');
    }

    public function test_tc_rid_024_resend_rotates_the_token_and_tc_rid_025_blocks_active_resend(): void
    {
        $owner = $this->logistics();
        $profile = $this->createdRider($owner, '024');
        $first = $this->emailedToken();
        $this->post(route('logistics.riders.activation.resend', $profile))->assertSessionHasNoErrors();
        $second = $this->emailedToken();
        $this->assertNotSame($first, $second);
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('rider.activation.show', $first))->assertOk()->assertSee('invalid or has already been used');
        $this->get(route('rider.activation.show', $second))->assertOk()->assertSee('Activate your Rider account');

        $this->post(route('rider.activation.complete', $second), ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026'])->assertRedirect(route('rider.login'));
        $this->actingAs($owner);
        $this->post(route('logistics.riders.activation.resend', $profile))->assertStatus(422);
        Mail::assertSentTimes(RiderActivationInvitation::class, 2);
    }

    public function test_tc_rid_028_owner_scoping_and_idor_protection_cover_management_actions(): void
    {
        $ownerA = $this->logistics('Bilis Hatid Express Inc.', 'ops-a@example.test');
        $ownerB = $this->logistics('Agila Cargo Movers Corp.', 'ops-b@example.test');
        $rider = $this->createdRider($ownerA, '028');

        $this->actingAs($ownerA)->get(route('logistics.riders', ['view' => $rider->id]))->assertOk()->assertSee('Pedro Jose Garcia')->assertSee($ownerA->name);
        $this->actingAs($ownerB)->get(route('logistics.riders'))->assertOk()->assertDontSee($rider->user->email);
        $this->actingAs($ownerB)->get(route('logistics.riders', ['view' => $rider->id]))->assertNotFound();
        $this->actingAs($ownerB)->put(route('logistics.riders.update', $rider), [])->assertNotFound();
        $this->actingAs($ownerB)->post(route('logistics.riders.activation.resend', $rider))->assertNotFound();
        $this->assertSame($ownerA->id, $rider->fresh()->logistics_user_id);
    }

    public function test_tc_rid_029_030_031_and_032_registration_and_role_access_are_blocked(): void
    {
        $this->get('/rider/register')->assertRedirect(route('rider.login'));
        $this->get('/register/rider')->assertRedirect(route('rider.login'));
        $this->post('/rider/register', [])->assertStatus(410);
        $this->post('/register/rider', [])->assertStatus(410);
        $this->get('/register')->assertOk()->assertDontSee('Apply as Rider');

        $owner = $this->logistics();
        $buyer = User::factory()->create(['is_active' => true])->assignRole('buyer');
        $seller = User::factory()->create(['is_active' => true])->assignRole('seller');
        foreach ([$buyer, $seller] as $user) {
            $this->actingAs($user)->get(route('logistics.riders'))->assertForbidden();
            $this->post(route('logistics.riders.store'), [])->assertForbidden();
        }
        $pending = $this->logistics('Pending Logistics', 'pending@example.test', 'pending');
        $this->actingAs($pending)->get(route('logistics.riders'))->assertForbidden();
        $this->post(route('logistics.riders.store'), [])->assertForbidden();

        $rider = User::factory()->create(['is_active' => true, 'registration_type' => 'rider', 'registration_status' => 'approved'])->assignRole('rider');
        RiderProfile::create(['user_id' => $rider->id, 'logistics_user_id' => $owner->id, 'account_status' => 'active', 'availability' => 'off_duty']);
        $this->actingAs($rider)->get(route('logistics.dashboard'))->assertForbidden();
        $this->get(route('seller.dashboard'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }
}
