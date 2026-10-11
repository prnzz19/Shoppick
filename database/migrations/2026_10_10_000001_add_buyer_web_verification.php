<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Nullable keys protect new website identities without rewriting legacy/shared-role data.
        // MySQL DDL can persist even when a later statement fails. Resume safely.
        foreach(['buyer_web_email'=>255,'buyer_web_phone'=>20,'buyer_web_signup_key'=>64] as $column=>$length){
            if(!Schema::hasColumn('users',$column))Schema::table('users',fn(Blueprint $table)=>$table->string($column,$length)->nullable()->unique());
        }
        if(Schema::hasTable('buyer_email_verifications'))return;
        Schema::create('buyer_email_verifications',function(Blueprint $table){
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            // DATETIME avoids legacy MySQL implicit TIMESTAMP zero-default behavior.
            $table->string('code_hash');$table->dateTime('expires_at');$table->dateTime('sent_at');
            $table->unsignedTinyInteger('attempts')->default(0);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('buyer_email_verifications');
        Schema::table('users',function(Blueprint $table){$table->dropUnique(['buyer_web_email']);$table->dropUnique(['buyer_web_phone']);$table->dropUnique(['buyer_web_signup_key']);$table->dropColumn(['buyer_web_email','buyer_web_phone','buyer_web_signup_key']);});
    }
};
