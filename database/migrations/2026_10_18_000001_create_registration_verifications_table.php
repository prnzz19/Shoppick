<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('registration_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 40)->default('registration');
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->useCurrent();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
            $table->timestamp('verified_at')->nullable();
            $table->unique(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_verifications');
    }
};
