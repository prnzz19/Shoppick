<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'action', 'target_type', 'target_id', 'details', 'ip', 'user_agent',
    ];

    protected $casts = ['details' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record($action, $targetType = null, $targetId = null, $details = null)
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
