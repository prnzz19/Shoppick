<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\BirthdayAge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BirthdayAgeFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_age_calculation_accounts_for_whether_the_birthday_has_occurred(): void
    {
        $today = Carbon::create(2026, 9, 10, 12);

        $this->assertSame(21, BirthdayAge::calculate(Carbon::parse('2005-09-10'), $today));
        $this->assertSame(20, BirthdayAge::calculate(Carbon::parse('2005-12-20'), $today));
        $this->assertNull(BirthdayAge::calculate(Carbon::parse('2030-01-01'), $today));
        $this->assertNull(BirthdayAge::calculate(null, $today));
    }

    public function test_buyer_and_seller_forms_share_readonly_age_component(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');

        $this->get(route('register.buyer'))->assertOk()
            ->assertSee('data-birthday-age', false)
            ->assertSee('name="age"', false)
            ->assertSee('readonly', false)
            ->assertSee('max="2026-09-10"', false);

        $this->get(route('register.seller'))->assertOk()
            ->assertSee('data-birthday-age', false)
            ->assertSee('Auto-calculated');
    }

    public function test_future_birthday_is_rejected_and_forged_age_is_ignored(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');
        Storage::fake('local');
        Role::create(['name'=>'Buyer', 'slug'=>'buyer', 'guard_name'=>'web']);

        $payload = [
            'first_name'=>'Secure', 'last_name'=>'Buyer', 'sex'=>'female',
            'birthday'=>'2030-01-01', 'age'=>'30', 'email'=>'secure-buyer@example.test',
            'phone'=>'09171234567', 'address_line'=>'1 Test Street', 'province'=>'Laguna',
            'city'=>'Cavinti', 'barangay'=>'Mahipon', 'postal_code'=>'4013', 'country'=>'PH',
            'valid_id'=>UploadedFile::fake()->create('id.pdf', 10, 'application/pdf'),
            'password'=>'password', 'password_confirmation'=>'password', 'terms'=>'1',
        ];

        $this->post(route('register.submit'), $payload)
            ->assertSessionHasErrors(['birthday'=>'Birthday cannot be in the future.']);

        $payload['birthday'] = '2005-12-20';
        $payload['valid_id'] = UploadedFile::fake()->create('id.pdf', 10, 'application/pdf');
        $this->post(route('register.submit'), $payload)->assertSessionHasNoErrors();

        $user = User::where('email', 'secure-buyer@example.test')->firstOrFail();
        $this->assertSame('2005-12-20', $user->birthday->toDateString());
        $this->assertSame(20, $user->age);
        $this->assertArrayNotHasKey('age', $user->getAttributes());
    }
}
