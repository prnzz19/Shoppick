<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
            if (! $adminRoleId) {
                return;
            }

            $owner = DB::table('users')
                ->join('role_user', 'users.id', '=', 'role_user.user_id')
                ->where('role_user.role_id', $adminRoleId)
                ->where('users.is_active', true)
                ->select('users.*')->first();

            if (! $owner || $owner->email !== 'superadmin@shoppick.test') {
                return;
            }

            $collision = DB::table('users')->where('email', 'admin@shoppick.test')
                ->where('id', '!=', $owner->id)->first();

            if ($collision) {
                $retiredEmail = 'retired-admin-'.$collision->id.'@shoppick.invalid';
                DB::table('users')->where('id', $collision->id)->update([
                    'email' => $retiredEmail,
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
            }

            DB::table('users')->where('id', $owner->id)->update([
                'email' => 'admin@shoppick.test',
                'name' => $owner->name === 'SHOPPICK Super Admin' ? 'SHOPPICK Admin' : $owner->name,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $owner = DB::table('users')->where('email', 'admin@shoppick.test')
                ->whereExists(function ($query) {
                    $query->selectRaw('1')->from('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                        ->whereColumn('role_user.user_id', 'users.id')->where('roles.slug', 'admin');
                })->first();

            if (! $owner || DB::table('users')->where('email', 'superadmin@shoppick.test')->exists()) {
                return;
            }

            DB::table('users')->where('id', $owner->id)->update(['email' => 'superadmin@shoppick.test', 'updated_at' => now()]);
            $retired = DB::table('users')->where('email', 'like', 'retired-admin-%@shoppick.invalid')->orderBy('id')->first();
            if ($retired && ! DB::table('users')->where('email', 'admin@shoppick.test')->exists()) {
                DB::table('users')->where('id', $retired->id)->update(['email' => 'admin@shoppick.test', 'updated_at' => now()]);
            }
        });
    }
};
