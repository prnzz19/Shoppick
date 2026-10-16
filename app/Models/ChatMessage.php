<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = ['conversation_id','sender_id','client_token','body','read_at'];
    protected $casts = ['read_at' => 'datetime'];
    public function conversation() { return $this->belongsTo(ChatConversation::class); }
    public function sender() { return $this->belongsTo(User::class, 'sender_id')->withTrashed(); }
}
