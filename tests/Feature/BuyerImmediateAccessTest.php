<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\User;
use App\Services\BuyerStatusReconciliation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MocksPhilippineLocations;
use Tests\TestCase;

class BuyerImmediateAccessTest extends TestCase
{
    use MocksPhilippineLocations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function registration(): array
    {
        return ['first_name' => 'New', 'last_name' => 'Buyer', 'sex' => 'female', 'birthday' => '2000-01-01', 'email' => 'immediate-buyer@example.test',
            'phone' => '09171234567', 'address_line' => '12 Test Street', 'region' => 'Region IV-A (CALABARZON)', 'region_code' => '0400000000',
            'province' => 'Laguna', 'province_code' => '0403400000', 'city' => 'Santa Cruz', 'city_code' => '0403426000', 'barangay' => 'Bubukal', 'barangay_code' => '0403426003',
            'postal_code' => '4009', 'country' => 'PH', 'terms' => '1', 'password' => 'BuyerPass123!', 'password_confirmation' => 'BuyerPass123!',
            'valid_id' => UploadedFile::fake()->create('test-id.pdf', 10, 'application/pdf')];
    }

    private function newBuyer(): User
    {
        $this->post(route('register.submit'), $this->registration())->assertRedirect(route('login'))->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Registration successful. You can now log in to your Buyer account.');

        return User::where('email', 'immediate-buyer@example.test')->firstOrFail();
    }

    public function test_registration_is_active_buyer_only_and_can_immediately_login_and_shop(): void
    {
        $payload = $this->registration() + ['roles' => ['admin', 'seller', 'rider', 'logistics'], 'role' => 'admin', 'registration_type' => 'seller', 'is_active' => false, 'registration_status' => 'pending'];
        $this->post(route('register.submit'), $payload)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $buyer = User::where('email', $payload['email'])->firstOrFail();
        $this->assertSame(['buyer'], $buyer->roles()->pluck('slug')->all());
        $this->assertTrue($buyer->is_active);
        $this->assertSame('approved', $buyer->registration_status);
        $this->assertTrue(Hash::check('BuyerPass123!', $buyer->password));
        $this->post(route('login.submit'), ['email' => $buyer->email, 'password' => 'BuyerPass123!'])->assertRedirect(route('home'))->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($buyer);
        $this->get(route('account.profile'))->assertOk();
        $this->get(route('cart.index'))->assertOk();
        $this->get(route('home'))->assertOk()->assertDontSee('waiting for administrator approval');
    }

    public function test_seller_application_keeps_buyer_login_and_only_approval_adds_seller(): void
    {
        $buyer = $this->newBuyer();
        $category = Category::create(['name' => 'Buyer Test', 'slug' => 'buyer-test', 'is_active' => true]);
        $this->actingAs($buyer)->post(route('seller.apply.store'), [
            'store_name' => 'Immediate Buyer Test Shop', 'store_description' => 'Testing role separation', 'category_id' => $category->id, 'phone' => $buyer->phone, 'address' => '12 Test Street, Bubukal, Santa Cruz, Laguna',
            'valid_id' => UploadedFile::fake()->create('id.pdf', 10, 'application/pdf'), 'business_permit' => UploadedFile::fake()->create('permit.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('seller.apply'))->assertSessionHasNoErrors();
        $application = $buyer->sellerApplications()->firstOrFail();
        $this->assertSame('pending', $application->status);
        $this->assertTrue($buyer->fresh()->is_active);
        $this->assertSame('approved', $buyer->fresh()->registration_status);
        $this->assertFalse($buyer->fresh()->isSeller());
        $this->post(route('logout'));
        $this->post(route('login.submit'), ['email' => $buyer->email, 'password' => 'BuyerPass123!'])->assertRedirect(route('seller.apply'))->assertSessionHasNoErrors();
        $this->get(route('account.profile'))->assertOk();
        $this->get(route('seller.dashboard'))->assertForbidden();
        $admin = User::factory()->create(['is_active' => true])->assignRole('admin');
        $this->actingAs($admin)->post(route('admin.sellers.applications.review', $application), ['status' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($buyer->fresh()->hasApprovedSellerAccess());
        $this->assertTrue($buyer->fresh()->isBuyer());
        $this->actingAs($buyer->fresh())->get(route('seller.dashboard'))->assertOk();
    }

    public function test_inactive_archived_rejected_and_approval_dependent_accounts_remain_blocked(): void
    {
        foreach ([
            ['registration_type' => 'buyer', 'registration_status' => 'approved', 'is_active' => false],
            ['registration_type' => 'buyer', 'registration_status' => 'pending', 'is_active' => false],
            ['registration_type' => 'buyer', 'registration_status' => 'rejected', 'is_active' => true],
            ['registration_type' => 'buyer', 'registration_status' => 'suspended', 'is_active' => false],
            ['registration_type' => 'seller', 'registration_status' => 'pending', 'is_active' => true],
        ] as $state) {
            $user = User::factory()->create($state + ['password' => Hash::make('BuyerPass123!')])->assignRole('buyer');
            $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'BuyerPass123!'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        $archived = User::factory()->create(['is_active' => true, 'registration_type' => 'buyer', 'registration_status' => 'approved', 'password' => Hash::make('BuyerPass123!')])->assignRole('buyer');
        $archived->delete();
        $this->post(route('login.submit'), ['email' => $archived->email, 'password' => 'BuyerPass123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($archived)->get(route('account.profile'))->assertRedirect(route('login'));
    }

    public function test_active_legacy_buyer_pending_metadata_does_not_require_an_admin_decision(): void
    {
        $buyer = User::factory()->create(['is_active' => true, 'registration_type' => 'buyer', 'registration_status' => 'pending', 'password' => Hash::make('BuyerPass123!')])->assignRole('buyer');
        $this->post(route('login.submit'), ['email' => $buyer->email, 'password' => 'BuyerPass123!'])->assertSessionHasNoErrors();
        $this->get(route('account.profile'))->assertOk();
        $this->assertSame('pending', $buyer->fresh()->registration_status, 'Login must not apply reconciliation.');
    }

    public function test_admin_users_show_active_buyers_without_an_approval_button(): void
    {
        $buyer = $this->newBuyer();
        $admin = User::factory()->create(['is_active' => true])->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.users.index', ['tab' => 'buyers']))->assertOk()->assertSee($buyer->email)->assertDontSee('Review Buyer registration');
        $this->get(route('admin.users.show', $buyer))->assertOk()->assertSee('Active')->assertDontSee('Approve Buyer');
        $this->get(route('admin.sellers.applications.index'))->assertOk();
    }

    private function legacyBuyer(): User
    {
        $buyer = User::factory()->create(['is_active' => false, 'registration_type' => 'buyer', 'registration_status' => 'pending',
            'first_name' => 'Legacy', 'last_name' => 'Buyer', 'birthday' => '2000-01-01', 'phone' => '09171234567', 'valid_id_path' => 'registration-documents/test.pdf'])->assignRole('buyer');
        $buyer->addresses()->create(['full_name' => 'Legacy Buyer', 'phone' => $buyer->phone, 'address_line' => '12 Test Street', 'province' => 'Laguna', 'city' => 'Santa Cruz', 'barangay' => 'Bubukal', 'postal_code' => '4009', 'is_default' => true]);

        return $buyer;
    }

    public function test_reconciliation_dry_run_defaults_to_no_writes_and_apply_requires_reviewed_ids(): void
    {
        $buyer = $this->legacyBuyer();
        $this->artisan('users:reconcile-buyer-status')->expectsOutputToContain('Eligible: 1; skipped: 0.')->expectsOutputToContain('No database records changed.')->assertSuccessful();
        $this->assertFalse($buyer->fresh()->is_active);
        $this->assertDatabaseCount('admin_activity_logs', 0);
        $this->artisan('users:reconcile-buyer-status', ['--apply' => true])->assertFailed();
        // This applies only to isolated in-memory test records, never the local database.
        $this->artisan('users:reconcile-buyer-status', ['--apply' => true, '--user' => [$buyer->id]])->assertSuccessful();
        $this->assertTrue($buyer->fresh()->is_active);
        $this->assertSame('approved', $buyer->fresh()->registration_status);
        $this->assertDatabaseHas('admin_activity_logs', ['target_id' => $buyer->id, 'action' => 'buyer.registration_status_reconciled']);
    }

    public function test_reconciliation_excludes_archives_admin_actions_other_roles_and_ambiguous_records(): void
    {
        // Legacy-column protection is tested without requiring the retired migration.
        if (! \Illuminate\Support\Facades\Schema::hasColumn('users', 'archived_at')) {
            \Illuminate\Support\Facades\Schema::table('users', fn (\Illuminate\Database\Schema\Blueprint $table) => $table->timestamp('archived_at')->nullable());
        }
        $reconciliation = app(BuyerStatusReconciliation::class);
        $eligible = $this->legacyBuyer();
        $this->assertNull($reconciliation->exclusion($eligible));
        $archived = $this->legacyBuyer();
        DB::table('users')->where('id', $archived->id)->update(['archived_at' => now()]);
        $this->assertNotNull($reconciliation->exclusion($archived->fresh()));
        $disabled = $this->legacyBuyer();
        AdminActivityLog::record('user.status', 'user', $disabled->id, ['is_active' => false]);
        $this->assertNotNull($reconciliation->exclusion($disabled));
        $reviewed = $this->legacyBuyer();
        $reviewed->update(['registration_review_notes' => 'Do not activate']);
        $this->assertNotNull($reconciliation->exclusion($reviewed));
        $special = $this->legacyBuyer()->assignRole('rider');
        $this->assertNotNull($reconciliation->exclusion($special));
        $ambiguous = $this->legacyBuyer();
        DB::table('users')->where('id', $ambiguous->id)->update(['updated_at' => now()->addMinute()]);
        $this->assertNotNull($reconciliation->exclusion($ambiguous->fresh()));
        $deleted = $this->legacyBuyer();
        $deleted->delete();
        $this->assertNotNull($reconciliation->exclusion($deleted));
        $this->assertFalse($reconciliation->apply($disabled->id));
        $this->assertFalse($disabled->fresh()->is_active);
    }

    public function test_profile_completion_cannot_reactivate_a_disabled_account(): void
    {
        $buyer = User::factory()->create(['is_active' => false, 'registration_type' => 'buyer', 'registration_status' => 'approved'])->assignRole('buyer');
        $this->actingAs($buyer)->post(route('profile.complete.update'), $this->registration())->assertForbidden();
        $this->assertFalse($buyer->fresh()->is_active);
    }
}
