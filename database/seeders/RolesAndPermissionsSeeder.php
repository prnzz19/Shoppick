<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Products & catalog
            ['name' => 'Manage Products', 'slug' => 'manage_products', 'group' => 'Products'],
            ['name' => 'Manage Categories', 'slug' => 'manage_categories', 'group' => 'Products'],
            ['name' => 'Manage Inventory', 'slug' => 'manage_inventory', 'group' => 'Products'],
            // Orders
            ['name' => 'Manage Orders', 'slug' => 'manage_orders', 'group' => 'Orders'],
            ['name' => 'Manage Sellers', 'slug' => 'manage_sellers', 'group' => 'Sellers'],
            // Promotions
            ['name' => 'Manage Promotions', 'slug' => 'manage_promotions', 'group' => 'Promotions'],
            // Reports
            ['name' => 'View Reports', 'slug' => 'view_reports', 'group' => 'Reports'],
            // Users
            ['name' => 'Manage Users', 'slug' => 'manage_users', 'group' => 'Users'],
            ['name' => 'Manage Roles', 'slug' => 'manage_roles', 'group' => 'Users'],
            // Settings
            ['name' => 'System Settings', 'slug' => 'system_settings', 'group' => 'System'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], [
                'name' => $perm['name'],
                'group' => $perm['group'],
                'guard_name' => 'web',
            ]);
        }

        // Super Admin — full access (managed by middleware bypass, but seed all for completeness)
        $superAdmin = Role::firstOrCreate(['slug' => 'super_admin'], [
            'name' => 'Super Admin',
            'guard_name' => 'web',
            'description' => 'Highest level of access.',
        ]);
        $superAdmin->permissions()->sync(Permission::pluck('id'));

        // Buyer — public storefront role, no admin permissions.
        Role::firstOrCreate(['slug' => 'buyer'], [
            'name' => 'Buyer',
            'guard_name' => 'web',
            'description' => 'Regular customer account.',
        ]);

        Role::firstOrCreate(['slug' => 'seller'], [
            'name' => 'Seller', 'guard_name' => 'web', 'description' => 'Approved marketplace seller.',
        ]);

        // Admin — default manageable-permission set (can be edited by Super Admin later).
        $admin = Role::firstOrCreate(['slug' => 'admin'], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'description' => 'Store operations manager.',
        ]);
        $admin->permissions()->sync(
            Permission::whereIn('slug', [
                'manage_products', 'manage_categories', 'manage_inventory',
                'manage_orders', 'manage_sellers', 'manage_promotions', 'view_reports',
            ])->pluck('id')
        );
    }
}
