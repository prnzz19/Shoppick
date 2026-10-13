<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BuyerSellerMessageAttachment extends Model
{
    protected $fillable = ['path', 'mime_type', 'file_size'];
    protected $hidden = ['path'];
    public function message(): BelongsTo { return $this->belongsTo(BuyerSellerMessage::class, 'message_id'); }
}
