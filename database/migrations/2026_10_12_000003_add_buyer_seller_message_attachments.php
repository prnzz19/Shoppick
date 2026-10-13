<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('buyer_seller_messages', function (Blueprint $table) {
            $table->uuid('client_token')->nullable();
            $table->unique(['conversation_id', 'sender_id', 'client_token'], 'chat_message_retry_unique');
        });
        Schema::create('buyer_seller_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('buyer_seller_messages')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime_type', 32);
            $table->unsignedInteger('file_size');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('buyer_seller_message_attachments');
        Schema::table('buyer_seller_messages', function (Blueprint $table) {
            $table->dropUnique('chat_message_retry_unique');
            $table->dropColumn('client_token');
        });
    }
};
