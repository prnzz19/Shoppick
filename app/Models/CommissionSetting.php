<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionSetting extends Model
{
    protected $fillable = ['is_enabled', 'default_rate'];
    protected $casts = ['is_enabled' => 'boolean', 'default_rate' => 'decimal:2'];
}
