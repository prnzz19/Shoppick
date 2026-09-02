<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_category_names_render_with_working_links_and_fallback_visuals(): void
    {
        foreach (['Electronics', 'Fashion', 'Beauty', 'Home & Living', 'Food', 'Sports', 'Gaming', 'School Supplies'] as $position => $name) {
            Category::create([
                'name' => $name,
                'slug' => str($name)->slug(),
                'sort_order' => $position + 1,
                'is_active' => true,
            ]);
        }

        $response = $this->get(route('home'))->assertOk();
        foreach (Category::all() as $category) {
            $response->assertSee($category->name)->assertSee(route('products.category', $category), false);
        }
        $response->assertSee('bg-gradient-to-br', false);
    }

    public function test_admin_with_category_permission_can_create_a_category_visible_to_buyers(): void
    {
        Storage::fake('public');
        $admin = $this->adminWithCategoryPermission();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Pet Supplies',
            'description' => 'Everyday picks for pets.',
            'sort_order' => 3,
            'is_active' => 1,
            'image' => $this->fakePng('pets.png'),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $category = Category::where('name', 'Pet Supplies')->firstOrFail();
        $this->assertSame('Everyday picks for pets.', $category->description);
        Storage::disk('public')->assertExists($category->image);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Pet Supplies')
            ->assertSee(route('products.category', $category), false)
            ->assertSee(asset('storage/'.$category->image), false);
    }

    public function test_active_and_inactive_categories_are_filtered_for_buyer_and_seller(): void
    {
        Category::create(['name' => 'Books', 'slug' => 'books', 'sort_order' => 1, 'is_active' => true]);
        Category::create(['name' => 'Temporary', 'slug' => 'temporary', 'sort_order' => 2, 'is_active' => false]);
        $seller = $this->approvedSeller();

        $this->get(route('home'))->assertOk()->assertSee('Books')->assertDontSee('Temporary');
        $this->actingAs($seller)->get(route('seller.products.create'))
            ->assertOk()
            ->assertSee('Books')
            ->assertDontSee('Temporary');
    }

    public function test_updating_an_image_keeps_the_category_id_and_removes_the_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('categories/old.png', 'old');
        $category = Category::create([
            'name' => 'Books', 'slug' => 'books', 'image' => 'categories/old.png', 'is_active' => true,
        ]);
        $admin = $this->adminWithCategoryPermission();

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Books & Reading',
            'description' => 'Stories and learning.',
            'sort_order' => 2,
            'is_active' => 1,
            'image' => $this->fakePng('books.png'),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $updated = Category::findOrFail($category->id);
        $this->assertSame('Books & Reading', $updated->name);
        Storage::disk('public')->assertMissing('categories/old.png');
        Storage::disk('public')->assertExists($updated->image);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_super_admin_has_its_own_category_management_url(): void
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)->get(route('superadmin.categories.index'))
            ->assertOk()
            ->assertSee('Categories');
    }

    public function test_admin_without_category_permission_cannot_mutate_categories(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Forbidden Category',
        ])->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Forbidden Category']);
    }

    private function adminWithCategoryPermission(): User
    {
        $permission = Permission::create([
            'name' => 'Manage Categories', 'slug' => 'manage_categories', 'guard_name' => 'web',
        ]);
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
        $role->permissions()->attach($permission);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['is_active' => true]);
        $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        Store::create([
            'user_id' => $seller->id,
            'seller_profile_id' => $profile->id,
            'name' => 'Category Test Store',
            'slug' => 'category-test-store',
            'status' => 'active',
        ]);

        return $seller;
    }

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );
    }
}
