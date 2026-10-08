<?php

namespace Tests\Feature;

use App\Http\Requests\SellerRegistrationRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\PhilippineGeographyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhilippineLocationResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.psgc.base_url' => 'https://psgc.test/api', 'services.psgc.cache_store' => 'array']);
        Cache::store('array')->flush();
        Http::preventStrayRequests();
    }

    private function provider(): void
    {
        Http::fake([
            'https://psgc.test/api/regions' => Http::response([
                ['code' => '0400000000', 'name' => 'Region IV-A (CALABARZON)'],
                ['code' => '1300000000', 'name' => 'National Capital Region (NCR)'],
            ]),
            'https://psgc.test/api/regions/0400000000/provinces' => Http::response([['code' => '0403400000', 'name' => 'Laguna']]),
            'https://psgc.test/api/provinces/0403400000/cities-municipalities' => Http::response([['code' => '0403426000', 'name' => 'Santa Cruz']]),
            'https://psgc.test/api/cities-municipalities/0403426000/barangays' => Http::response([['code' => '0403426003', 'name' => 'Bubukal']]),
            'https://psgc.test/api/regions/1300000000/provinces' => Http::response([]),
            'https://psgc.test/api/regions/1300000000/cities-municipalities' => Http::response([['code' => '1380600000', 'name' => 'City of Manila']]),
            'https://psgc.test/api/cities-municipalities/1380600000/barangays' => Http::response([['code' => '1380601001', 'name' => 'Barangay 1']]),
        ]);
    }

    private function address(): array
    {
        return ['region_code' => '0400000000', 'province_code' => '0403400000', 'city_code' => '0403426000', 'barangay_code' => '0403426003',
            'region' => 'Forged region', 'province' => 'Forged province', 'city' => 'Forged city', 'barangay' => 'Forged barangay',
            'address_line' => '12 Test Street', 'postal_code' => '4009'];
    }

    public function test_known_hierarchy_is_normalized_cached_and_filtered(): void
    {
        $this->provider();
        $this->getJson(route('locations.regions'))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(route('locations.provinces', '0400000000'))->assertOk()->assertJsonPath('data.0.region_code', '0400000000')->assertJsonPath('data.0.name', 'Laguna');
        $this->getJson(route('locations.province-cities', '0403400000'))->assertOk()->assertJsonPath('data.0.province_code', '0403400000')->assertJsonPath('data.0.name', 'Santa Cruz');
        $this->getJson(route('locations.barangays', '0403426000'))->assertOk()->assertJsonPath('data.0.city_code', '0403426000')->assertJsonPath('data.0.name', 'Bubukal');
        $this->getJson(route('locations.regions'))->assertOk();
        $normalized = app(PhilippineGeographyService::class)->normalizeAddress($this->address());
        $this->assertSame('Bubukal', $normalized['barangay']);
        $this->assertSame('12 Test Street', $normalized['address_line']);
        $this->assertSame('4009', $normalized['postal_code']);
        Http::assertSentCount(4);
    }

    public function test_invalid_parent_codes_are_rejected_without_fetching_children(): void
    {
        $this->provider();
        $this->getJson(route('locations.provinces', 'bad-code'))->assertStatus(422);
        $this->getJson(route('locations.province-cities', 'bad-code'))->assertStatus(422);
        $this->getJson(route('locations.barangays', 'bad-code'))->assertStatus(422);
        Http::assertNothingSent();
        $this->getJson(route('locations.provinces', '9999999999'))->assertStatus(422);
        Http::assertSentCount(1);
    }

    public function test_nonexistent_numeric_parent_is_rejected_safely(): void
    {
        Http::fake(['*' => Http::response([], 404)]);
        $this->getJson(route('locations.barangays', '9999999999'))->assertStatus(422);
    }

    public function test_provider_failure_returns_friendly_503_and_uses_a_short_cooldown(): void
    {
        Http::fake(['*' => Http::response(['internal' => 'secret-provider-details'], 500)]);
        $this->getJson(route('locations.regions'))->assertStatus(503)->assertJsonStructure(['message'])->assertDontSee('secret-provider-details');
        $this->getJson(route('locations.regions'))->assertStatus(503);
        Http::assertSentCount(2);
        $this->get(route('register.buyer'))->assertOk()->assertSee('data-location-retry', false);
    }

    public function test_last_successful_lists_survive_expiry_and_provider_outage(): void
    {
        $this->provider();
        $service = app(PhilippineGeographyService::class);
        $service->normalizeAddress($this->address());
        $this->travel(86401)->seconds();
        Http::fake(['*' => Http::response([], 503)]);
        $normalized = $service->normalizeAddress($this->address());
        $this->assertSame('Santa Cruz', $normalized['city']);
        $this->assertSame('Bubukal', $normalized['barangay']);
        $this->getJson(route('locations.regions'))->assertOk();
        $this->travelBack();
    }

    public function test_malformed_data_is_not_cached_as_a_success(): void
    {
        Http::fake(['*' => Http::response([['unexpected' => 'value']])]);
        $this->getJson(route('locations.regions'))->assertStatus(503);
        $this->assertFalse(Cache::store('array')->has('psgc:v2:'.sha1('https://psgc.test/api/regions').':last-success'));
    }

    public function test_mismatched_codes_are_rejected_and_ncr_needs_no_province(): void
    {
        $this->provider();
        $service = app(PhilippineGeographyService::class);
        try {
            $service->normalizeAddress(array_replace($this->address(), ['province_code' => '9999999999']));
            $this->fail('Mismatched province should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('province', $exception->errors());
        }
        $ncr = $service->normalizeAddress(['region_code' => '1300000000', 'city_code' => '1380600000', 'barangay_code' => '1380601001']);
        $this->assertNull($ncr['province_code']);
        $this->assertSame('', $ncr['province']);
    }

    public function test_name_only_values_are_resolved_but_arbitrary_names_and_missing_parents_are_rejected(): void
    {
        $this->provider();
        $service = app(PhilippineGeographyService::class);
        $legacy = ['region' => 'Region IV-A (CALABARZON)', 'province' => 'LAGUNA', 'city' => 'SANTA CRUZ', 'barangay' => 'BUBUKAL'];
        $this->assertSame('0403426003', $service->normalizeAddress($legacy)['barangay_code']);
        foreach ([array_replace($legacy, ['barangay' => 'Invented location']), array_diff_key($legacy, ['region' => true])] as $invalid) {
            try {
                $service->normalizeAddress($invalid);
                $this->fail('An untrusted hierarchy must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_account_saves_canonical_names_and_preserves_other_fields(): void
    {
        $this->provider();
        Role::create(['name' => 'Buyer', 'slug' => 'buyer', 'guard_name' => 'web']);
        $buyer = User::factory()->create(['is_active' => true]);
        $buyer->assignRole('buyer');
        $payload = $this->address() + ['full_name' => 'Yuan Test', 'phone' => '09171234567', 'label' => 'Home'];
        $this->actingAs($buyer)->post(route('account.addresses.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('addresses', ['user_id' => $buyer->id, 'region' => 'Region IV-A (CALABARZON)', 'province' => 'Laguna', 'city' => 'Santa Cruz', 'barangay' => 'Bubukal', 'address_line' => '12 Test Street', 'postal_code' => '4009', 'full_name' => 'Yuan Test']);
        $this->post(route('account.addresses.store'), array_replace($payload, ['city_code' => '9999999999']))->assertSessionHasErrors('city');
        $this->get(route('account.addresses'))->assertOk()->assertSee('previous.address_id', false);
    }

    public function test_registration_normalizes_names_and_keeps_personal_validation(): void
    {
        $this->provider();
        Storage::fake('local');
        Role::create(['name' => 'Buyer', 'slug' => 'buyer', 'guard_name' => 'web']);
        $payload = $this->address() + ['first_name' => 'Yuan', 'last_name' => 'Test', 'sex' => 'male', 'birthday' => '2000-01-01', 'email' => 'yuan-address@example.test', 'phone' => '09171234567', 'country' => 'PH', 'terms' => '1', 'password' => 'password', 'password_confirmation' => 'password', 'valid_id' => UploadedFile::fake()->create('id.pdf', 10, 'application/pdf')];
        $this->post(route('register.submit'), array_replace($payload, ['phone' => 'invalid']))->assertSessionHasErrors('phone');
        $this->post(route('register.submit'), $payload)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('addresses', ['province' => 'Laguna', 'barangay' => 'Bubukal', 'postal_code' => '4009']);
    }

    public function test_seller_request_validates_the_store_hierarchy_separately(): void
    {
        $this->provider();
        $request = SellerRegistrationRequest::create('/', 'POST', ['same_address' => '0']);
        $store = [];
        foreach ($this->address() as $field => $value) {
            $store['store_'.$field] = $value;
        }
        $validator = Validator::make($this->address() + array_replace($store, ['store_province_code' => '9999999999']), []);
        $request->withValidator($validator);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('store_province'));
        $rules = $request->rules();
        $this->assertContains('accepted', $rules['seller_terms']);
        $this->assertContains('required', $rules['business_permit']);
    }

    public function test_component_exposes_saved_codes_names_and_a_same_origin_endpoint(): void
    {
        $html = view('components.philippine-location-fields', [
            'errors' => new ViewErrorBag,
            'region' => 'Region IV-A (CALABARZON)', 'regionCode' => '0400000000', 'province' => 'Laguna', 'provinceCode' => '0403400000',
            'city' => 'Santa Cruz', 'cityCode' => '0403426000', 'barangay' => 'Bubukal', 'barangayCode' => '0403426003',
        ])->render();
        $this->assertStringContainsString('data-initial-city-code="0403426000"', $html);
        $this->assertStringContainsString('data-initial-barangay="Bubukal"', $html);
        $this->assertStringContainsString('data-endpoint="/api/philippine-locations"', $html);
    }

    public function test_profile_completion_uses_the_saved_address_and_old_values_take_precedence(): void
    {
        $this->provider();
        $buyer = User::factory()->create(['registration_status' => 'incomplete']);
        $address = app(PhilippineGeographyService::class)->normalizeAddress($this->address());
        $buyer->addresses()->create($address + ['full_name' => $buyer->name, 'phone' => '09171234567', 'is_default' => true]);
        $this->actingAs($buyer)->get(route('profile.complete'))->assertOk()
            ->assertSee('data-initial-city-code="0403426000"', false)
            ->assertSee('value="12 Test Street"', false)->assertSee('value="4009"', false);
        $this->withSession(['_old_input' => ['address_line' => 'Previously entered street', 'postal_code' => '4013']])
            ->get(route('profile.complete'))->assertOk()
            ->assertSee('value="Previously entered street"', false)->assertSee('value="4013"', false);
    }
}
