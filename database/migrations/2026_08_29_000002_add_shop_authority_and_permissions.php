<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('status_changed_by')->nullable()->after('status_reason')->constrained('users')->nullOnDelete();
        });

        $permissions = [
            ['View Shops','view_shops'], ['Review Shops','review_shops'], ['Approve Shops','approve_shops'],
            ['Reject Shops','reject_shops'], ['Restrict Shops','restrict_shops'], ['Suspend Shops','suspend_shops'],
            ['Reactivate Shops','reactivate_shops'], ['View Shop Reports','view_shop_reports'],
            ['View Shop Violations','view_shop_violations'], ['Add Shop Notes','add_shop_notes'],
        ];
        foreach ($permissions as [$name,$slug]) {
            Permission::firstOrCreate(['slug'=>$slug], ['name'=>$name,'group'=>'Shops','guard_name'=>'web']);
        }
        Role::where('slug','super_admin')->first()?->permissions()->syncWithoutDetaching(Permission::whereIn('slug',array_column($permissions,1))->pluck('id'));
        Role::where('slug','admin')->first()?->permissions()->syncWithoutDetaching(Permission::whereIn('slug',['view_shops','view_shop_reports','view_shop_violations'])->pluck('id'));
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->dropConstrainedForeignId('status_changed_by'));
    }
};
