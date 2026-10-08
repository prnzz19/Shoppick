<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoriesWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $permission = Permission::create(['name' => 'Manage Categories', 'slug' => 'manage_categories', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
        $role->permissions()->attach($permission);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_workspace_renders_ordered_parents_children_counts_and_existing_actions(): void
    {
        $admin = $this->admin();
        $electronics = Category::create(['name' => 'Electronics', 'slug' => 'electronics', 'sort_order' => 1, 'is_active' => true]);
        $demo = Category::create(['name' => 'Logistics Demo', 'slug' => 'logistics-demo', 'sort_order' => 2, 'is_active' => false]);
        foreach (['Phones', 'Laptops', 'Audio', 'Accessories'] as $name) {
            $child = Category::create(['name' => $name, 'slug' => strtolower($name), 'parent_id' => $electronics->id, 'is_active' => true]);
            Product::create(['category_id' => $child->id, 'name' => $name.' Item', 'slug' => strtolower($name).'-item', 'price' => 10, 'stock' => 1]);
        }
        $response = $this->actingAs($admin)->get(route('admin.categories.index'))->assertOk()
            ->assertSee('Category list')->assertSee('Category details')->assertSee('Search categories...')
            ->assertSee('Electronics')->assertSee('Logistics Demo')->assertSee('Inactive')
            ->assertSee('4 subcategories')->assertSee('1 product')->assertSee('+ Add Category')
            ->assertSee('Edit Category')->assertSee('Deactivate')->assertSee('Activate')->assertSee('Delete Category')
            ->assertSee('data-confirm-title="Delete this category?"', false)
            ->assertSee('data-category-id="'.$electronics->id.'"', false)
            ->assertSee('category-detail-'.$electronics->id, false)
            ->assertSee(route('admin.categories.toggle', $demo), false)
            ->assertSee(route('admin.categories.destroy', $electronics), false)
            ->assertSee(route('admin.categories.store'), false)
            ->assertViewHas('categories', fn ($categories) => $categories->pluck('id')->all() === [$electronics->id, $demo->id]);
        foreach (['Phones', 'Laptops', 'Audio', 'Accessories'] as $name) {
            $response->assertSee($name)->assertSee('aria-label="Edit '.$name.'"', false);
        }
    }

    public function test_workspace_handles_empty_catalog_and_category_without_children(): void
    {
        $this->actingAs($this->admin())->get(route('admin.categories.index'))->assertOk()
            ->assertSee('No categories yet.')->assertSee('Add a category to get started.')->assertSee('+ Add Category');
        Category::create(['name' => 'Food', 'slug' => 'food', 'is_active' => true]);
        $this->get(route('admin.categories.index'))->assertOk()->assertSee('No subcategories yet.')
            ->assertSee('No categories match your search.');
    }

    public function test_parent_and_child_edit_toggle_and_delete_rules_remain_functional(): void
    {
        $this->actingAs($this->admin());
        $parent = Category::create(['name' => 'Fashion', 'slug' => 'fashion', 'is_active' => true]);
        $child = Category::create(['name' => 'Shoes', 'slug' => 'shoes', 'parent_id' => $parent->id, 'is_active' => true]);
        foreach ([$parent, $child] as $category) {
            $this->put(route('admin.categories.update', $category), ['name' => $category->name.' Updated', 'sort_order' => 4, 'is_active' => 1])
                ->assertSessionHasNoErrors()->assertSessionHas('success');
            $this->assertSame($category->name.' Updated', $category->fresh()->name);
            $this->post(route('admin.categories.toggle', $category))->assertSessionHas('success');
            $this->assertFalse($category->fresh()->is_active);
        }
        $this->assertSame($parent->id, $child->fresh()->parent_id);
        Product::create(['category_id' => $child->id, 'name' => 'Shoes Item', 'slug' => 'shoes-item', 'price' => 10, 'stock' => 1]);
        $this->delete(route('admin.categories.destroy', $child))->assertSessionHas('error');
        $this->assertModelExists($child);
        $empty = Category::create(['name' => 'Empty', 'slug' => 'empty']);
        $this->delete(route('admin.categories.destroy', $empty))->assertSessionHas('success');
        $this->assertModelMissing($empty);
    }

    public function test_users_without_permission_cannot_access_or_mutate_categories(): void
    {
        $category = Category::create(['name' => 'Beauty', 'slug' => 'beauty', 'is_active' => true]);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.categories.index'))->assertForbidden();
        $seller = User::factory()->create(['is_active' => true]);
        $seller->assignRole('seller');
        $this->actingAs($seller)->get(route('admin.categories.index'))->assertForbidden();
        $this->post(route('admin.categories.store'), ['name' => 'Forbidden'])->assertForbidden();
        $this->put(route('admin.categories.update', $category), ['name' => 'Forbidden'])->assertForbidden();
        $this->post(route('admin.categories.toggle', $category))->assertForbidden();
        $this->delete(route('admin.categories.destroy', $category))->assertForbidden();
        $this->assertSame('Beauty', $category->fresh()->name);
        $this->assertTrue($category->fresh()->is_active);
    }
}
