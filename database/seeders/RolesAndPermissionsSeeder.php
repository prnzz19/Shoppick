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
            ['name' => 'View Shops', 'slug' => 'view_shops', 'group' => 'Shops'],
            ['name' => 'Review Shops', 'slug' => 'review_shops', 'group' => 'Shops'],
            ['name' => 'Approve Shops', 'slug' => 'approve_shops', 'group' => 'Shops'],
            ['name' => 'Reject Shops', 'slug' => 'reject_shops', 'group' => 'Shops'],
            ['name' => 'Restrict Shops', 'slug' => 'restrict_shops', 'group' => 'Shops'],
            ['name' => 'Suspend Shops', 'slug' => 'suspend_shops', 'group' => 'Shops'],
            ['name' => 'Reactivate Shops', 'slug' => 'reactivate_shops', 'group' => 'Shops'],
            ['name' => 'View Shop Reports', 'slug' => 'view_shop_reports', 'group' => 'Shops'],
            ['name' => 'View Shop Violations', 'slug' => 'view_shop_violations', 'group' => 'Shops'],
            ['name' => 'Add Shop Notes', 'slug' => 'add_shop_notes', 'group' => 'Shops'],
            // Promotions
            ['name' => 'Manage Promotions', 'slug' => 'manage_promotions', 'group' => 'Promotions'],
            // Reports
            ['name' => 'View Reports', 'slug' => 'view_reports', 'group' => 'Reports'],
            ['name' => 'Manage Report Cases', 'slug' => 'manage_reports', 'group' => 'Moderation'],
            ['name' => 'Moderate Products', 'slug' => 'moderate_products', 'group' => 'Moderation'],
            // Users
            ['name' => 'Manage Users', 'slug' => 'manage_users', 'group' => 'Users'],
            ['name' => 'Manage Roles', 'slug' => 'manage_roles', 'group' => 'Users'],
            // Settings
            ['name' => 'System Settings', 'slug' => 'system_settings', 'group' => 'System'],
            ['name'=>'View Logistics Dashboard','slug'=>'view_logistics_dashboard','group'=>'Logistics'],
            ['name'=>'View Shipments','slug'=>'view_shipments','group'=>'Logistics'],
            ['name'=>'Manage Shipments','slug'=>'manage_shipments','group'=>'Logistics'],
            ['name'=>'Assign Shipments','slug'=>'assign_shipments','group'=>'Logistics'],
            ['name'=>'Manage Fleet','slug'=>'manage_fleet','group'=>'Logistics'],
            ['name'=>'Manage Riders','slug'=>'manage_riders','group'=>'Logistics'],
            ['name'=>'Manage Hubs','slug'=>'manage_hubs','group'=>'Logistics'],
            ['name'=>'Review POD','slug'=>'review_pod','group'=>'Logistics'],
            ['name'=>'Manage Logistics Billing','slug'=>'manage_logistics_billing','group'=>'Logistics'],
            ['name'=>'View Logistics Reports','slug'=>'view_logistics_reports','group'=>'Logistics'],
            ['name'=>'Manage Logistics Settings','slug'=>'manage_logistics_settings','group'=>'Logistics'],
            ['name'=>'Manage Logistics AI','slug'=>'manage_ai_logistics','group'=>'Logistics'],
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
        $logistics=Role::firstOrCreate(['slug'=>'logistics'],['name'=>'Logistics','guard_name'=>'web','description'=>'Delivery operations team.']);
        $logistics->permissions()->sync(Permission::where('group','Logistics')->pluck('id'));
        Role::firstOrCreate(['slug'=>'rider'],['name'=>'Rider','guard_name'=>'web','description'=>'Assigned final-mile delivery rider.']);

        // Admin — default manageable-permission set (can be edited by Super Admin later).
        $admin = Role::firstOrCreate(['slug' => 'admin'], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'description' => 'Store operations manager.',
        ]);
        $admin->permissions()->sync(
            Permission::whereIn('slug', [
                'manage_products', 'manage_categories', 'manage_inventory',
                'manage_orders', 'manage_sellers', 'manage_promotions', 'view_reports', 'manage_reports', 'moderate_products',
                'view_shops', 'review_shops', 'approve_shops', 'reject_shops',
                'restrict_shops', 'suspend_shops', 'reactivate_shops',
                'view_shop_reports', 'view_shop_violations', 'add_shop_notes',
            ])->pluck('id')
        );
    }
}
