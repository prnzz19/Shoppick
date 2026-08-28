<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function selectedItems()
    {
        return $this->items()->where('selected', true)->get();
    }

    public function countSelectedItems(): int
    {
        return $this->items()->where('selected', true)->sum('quantity');
    }
}
