<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $seller = DB::table('roles')->where('slug','seller')->value('id');
            if (!$seller) return;
            $buyer = DB::table('roles')->where('slug','buyer')->value('id');
            if (!$buyer) $buyer = DB::table('roles')->insertGetId(['slug'=>'buyer','name'=>'Buyer','guard_name'=>'web','created_at'=>now(),'updated_at'=>now()]);
            $ids = DB::table('role_user')->where('role_id',$seller)
                ->whereIn('user_id',DB::table('seller_profiles')->where('status','approved')->select('user_id'))
                ->pluck('user_id');
            foreach ($ids as $id) DB::table('role_user')->insertOrIgnore(['user_id'=>$id,'role_id'=>$buyer]);
        });
    }
    public function down(): void
    {
        // Buyer capabilities are retained: rollback must not revoke users' shopping access.
    }
};
