<?php

namespace Tests\Feature;

use App\Mail\SellerApplicationApproved;
use App\Mail\SellerApplicationRejected;
use App\Models\{Category, Permission, SellerApplication, User};
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SellerApprovalEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $admin;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->buyer = User::factory()->create([
            'name' => 'Verified Buyer Seller',
            'email' => 'verified-buyer@example.test',
            'email_verified_at' => now(),
            'is_active' => true,
            'registration_type' => 'buyer',
            'registration_status' => 'approved',
        ])->assignRole('buyer');
        $this->admin = User::factory()->create(['is_active' => true])->assignRole('admin');
        $this->category = Category::create(['name' => 'Seller Email Test', 'slug' => 'seller-email-test', 'is_active' => true]);
    }

    private function application(string $status = 'pending'): SellerApplication
    {
        return SellerApplication::create([
            'user_id' => $this->buyer->id,
            'store_name' => 'Verified Buyer Shop',
            'category_id' => $this->category->id,
            'store_description' => 'A verified seller email test shop.',
            'phone' => '09171234567',
            'address' => '1 Seller Street, Santa Cruz, Laguna',
            'seller_type' => 'Individual',
            'tin' => '123-456-789-000',
            'status' => $status,
        ]);
    }

    public function test_approved_seller_queues_one_branded_email_with_existing_buyer_details(): void
    {
        $application = $this->application();

        $this->actingAs($this->admin)->post(route('admin.sellers.applications.review', $application), [
            'status' => 'approved',
        ])->assertSessionHasNoErrors();

        Mail::assertQueued(SellerApplicationApproved::class, function (SellerApplicationApproved $mail) {
            return $mail->sellerName === 'Verified Buyer Seller'
                && $mail->shopName === 'Verified Buyer Shop'
                && $mail->hasTo('verified-buyer@example.test')
                && str_ends_with($mail->sellerCenterUrl, '/seller/dashboard');
        });
        Mail::assertQueuedCount(1);
        $approvalMail = new SellerApplicationApproved('Verified Buyer Seller', 'Verified Buyer Shop', 'https://shoppickmarket.shop/seller');
        $rendered = $approvalMail->render();
        $this->assertSame('Your SHOPPICK Seller Application Has Been Approved!', $approvalMail->subject);
        $this->assertStringContainsString('Verified Buyer Shop', $rendered);
        $this->assertStringContainsString('successfully reviewed and approved', $rendered);
        $this->assertStringContainsString('Go to Seller Center', $rendered);
        $this->assertStringContainsString('SHOPPICK Seller Support Team', $rendered);
        $this->assertTrue($this->buyer->fresh()->isBuyer());
        $this->assertTrue($this->buyer->fresh()->isSeller());
    }

    public function test_repeated_approval_does_not_duplicate_the_email(): void
    {
        $application = $this->application();
        $review = fn () => $this->actingAs($this->admin)->post(route('admin.sellers.applications.review', $application), ['status' => 'approved']);

        $review()->assertSessionHasNoErrors();
        $review()->assertSessionHasNoErrors();

        Mail::assertQueuedCount(1);
    }

    public function test_rejection_queues_reason_and_resubmission_link_without_documents(): void
    {
        $application = $this->application();

        $this->actingAs($this->admin)->post(route('admin.sellers.applications.review', $application), [
            'status' => 'rejected',
            'review_notes' => 'Permit does not match the submitted business information.',
        ])->assertSessionHasNoErrors();

        Mail::assertQueued(SellerApplicationRejected::class, function (SellerApplicationRejected $mail) {
            return $mail->decision === 'rejected'
                && $mail->shopName === 'Verified Buyer Shop'
                && $mail->hasTo('verified-buyer@example.test')
                && str_contains($mail->reason, 'Permit does not match')
                && str_ends_with($mail->resubmitUrl, '/seller/apply');
        });
        Mail::assertQueuedCount(1);
        $rejectionMail = new SellerApplicationRejected('Verified Buyer Seller', 'Verified Buyer Shop', 'Permit does not match.', 'https://shoppickmarket.shop/seller/apply', 'rejected');
        $rendered = $rejectionMail->render();
        $this->assertSame('An Important Update Regarding Your SHOPPICK Seller Application', $rejectionMail->subject);
        $this->assertStringContainsString('Permit does not match.', $rendered);
        $this->assertStringContainsString('Application Status:</strong> Rejected', $rendered);
        $this->assertStringNotContainsString('valid_id_path', $rendered);
    }

    public function test_final_rejection_does_not_promise_resubmission(): void
    {
        $mail = new SellerApplicationRejected('Verified Buyer Seller', 'Verified Buyer Shop', 'The application did not meet the required criteria.', 'https://shoppickmarket.shop/seller/apply', 'rejected');
        $rendered = $mail->render();

        $this->assertSame('An Important Update Regarding Your SHOPPICK Seller Application', $mail->subject);
        $this->assertStringContainsString('Application Status:</strong> Rejected', $rendered);
        $this->assertStringNotContainsString('Review and Resubmit', $rendered);
        $this->assertStringNotContainsString('This is not a final rejection', $rendered);
    }

    public function test_unauthorized_review_does_not_queue_email_and_pending_application_does_not_email(): void
    {
        $application = $this->application();
        $this->actingAs($this->buyer)->post(route('admin.sellers.applications.review', $application), ['status' => 'approved'])->assertForbidden();
        Mail::assertNothingQueued();
    }

    public function test_mail_queue_failure_does_not_reverse_approved_seller_access(): void
    {
        $application = $this->application();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));

        $this->actingAs($this->admin)->post(route('admin.sellers.applications.review', $application), ['status' => 'approved'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seller_applications', ['id' => $application->id, 'status' => 'approved']);
        $this->assertTrue($this->buyer->fresh()->hasApprovedSellerAccess());
    }

    public function test_unverified_buyer_approval_commits_but_skips_email_safely(): void
    {
        $this->buyer->forceFill(['email_verified_at' => null])->save();
        $application = $this->application();

        $this->actingAs($this->admin)->post(route('admin.sellers.applications.review', $application), ['status' => 'approved'])->assertSessionHasNoErrors();

        Mail::assertNothingQueued();
        $this->assertDatabaseHas('seller_applications', ['id' => $application->id, 'status' => 'approved']);
        $this->assertTrue($this->buyer->fresh()->hasApprovedSellerAccess());
    }
}
