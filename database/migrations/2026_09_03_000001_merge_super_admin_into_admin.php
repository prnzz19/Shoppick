<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $admin = DB::table('roles')->where('slug', 'admin')->first();
            $legacy = DB::table('roles')->where('slug', 'super_admin')->first();

            // On a brand-new unseeded database there are no role rows yet; the
            // role seeder creates the canonical five-role set afterward.
            if (! $admin && ! $legacy) {
                return;
            }

            if (! $admin) {
                $adminId = DB::table('roles')->insertGetId([
                    'name' => 'Admin',
                    'slug' => 'admin',
                    'guard_name' => 'web',
                    'description' => 'Highest SHOPPICK system authority.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $admin = (object) ['id' => $adminId];
            } else {
                DB::table('roles')->where('id', $admin->id)->update([
                    'name' => 'Admin',
                    'description' => 'Highest SHOPPICK system authority.',
                    'updated_at' => now(),
                ]);
            }

            $legacyUserIds = $legacy
                ? DB::table('role_user')->where('role_id', $legacy->id)->orderBy('user_id')->pluck('user_id')
                : collect();
            $existingAdminIds = DB::table('role_user')->where('role_id', $admin->id)->orderBy('user_id')->pluck('user_id');

            // The existing Super Admin identity is the system owner. Preserve that
            // user row and all relationships while changing only its RBAC assignment.
            $ownerId = $legacyUserIds->first()
                ?? $existingAdminIds->first();

            if ($legacy) {
                $permissionIds = DB::table('permission_role')
                    ->whereIn('role_id', [$admin->id, $legacy->id])
                    ->pluck('permission_id')->unique();
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $admin->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($ownerId) {
                DB::table('role_user')->insertOrIgnore([
                    'role_id' => $admin->id,
                    'user_id' => $ownerId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $duplicateAdminIds = DB::table('role_user')
                    ->where('role_id', $admin->id)
                    ->where('user_id', '!=', $ownerId)
                    ->pluck('user_id');

                // Preserve duplicate user rows and their history, but remove their
                // administrative authority and deactivate them for safe review.
                if ($duplicateAdminIds->isNotEmpty()) {
                    DB::table('role_user')->where('role_id', $admin->id)
                        ->whereIn('user_id', $duplicateAdminIds)->delete();
                    DB::table('users')->whereIn('id', $duplicateAdminIds)->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($legacy) {
                DB::table('role_user')->where('role_id', $legacy->id)->delete();
                DB::table('roles')->where('id', $legacy->id)->delete();
            }

            if (Schema::hasColumn('seller_applications', 'status')) {
                DB::table('seller_applications')
                    ->whereIn('status', ['escalated', 'awaiting_final_review', 'pending_superadmin_review'])
                    ->update([
                        'status' => 'pending',
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('roles')->insertOrIgnore([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'guard_name' => 'web',
            'description' => 'Legacy role restored by rollback; user assignments are intentionally not inferred.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
