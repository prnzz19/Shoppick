<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerApplication extends Model
{
    protected $fillable = ['user_id', 'store_name', 'category_id', 'store_description', 'phone', 'address',
        'business_information', 'valid_id_path', 'business_permit_path', 'logo', 'banner', 'status', 'admin_recommendation', 'admin_review_notes',
        'admin_reviewed_by', 'admin_reviewed_at', 'escalated_by', 'escalation_reason', 'escalated_at',
        'review_notes', 'reviewed_by', 'reviewed_at'];

    protected $hidden = ['valid_id_path', 'business_permit_path'];

    protected $casts = ['admin_reviewed_at' => 'datetime', 'escalated_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function adminReviewer() { return $this->belongsTo(User::class, 'admin_reviewed_by'); }
    public function escalator() { return $this->belongsTo(User::class, 'escalated_by'); }
    public function category() { return $this->belongsTo(Category::class); }
}
