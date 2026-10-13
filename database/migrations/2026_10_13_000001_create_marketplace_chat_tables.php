<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('identity_key', 100)->unique();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->index(['type', 'store_id', 'last_message_at']);
            $table->index(['type', 'buyer_id', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('client_token');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['sender_id', 'client_token']);
            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'read_at', 'sender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
