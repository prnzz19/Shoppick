<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_seller_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['buyer_id', 'store_id']);
            $table->index(['store_id', 'last_message_at']);
        });

        Schema::create('buyer_seller_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('buyer_seller_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('context_type', 20)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('context_key', 64)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'context_key']);
            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'sender_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_seller_messages');
        Schema::dropIfExists('buyer_seller_conversations');
    }
};
