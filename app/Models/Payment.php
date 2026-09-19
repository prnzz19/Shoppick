<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'method', 'status', 'reference', 'transaction_id',
        'gateway', 'details', 'amount', 'paid_at', 'collected_by', 'collected_at',
        'remittance_status', 'remitted_at', 'remitted_by',
    ];

    protected $casts = ['details' => 'array', 'paid_at' => 'datetime', 'collected_at' => 'datetime', 'remitted_at'=>'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function collector(){return $this->belongsTo(User::class,'collected_by');}
    public function remitter(){return $this->belongsTo(User::class,'remitted_by');}
}
