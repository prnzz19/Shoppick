<?php

namespace App\Models;

use App\Traits\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'password',
        'is_active',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function hasCompleteBuyerProfile(): bool
    {
        return filled($this->phone) && $this->addresses()->exists();
    }

    public function wishlist()
    {
        return $this->hasOne(Wishlist::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function sellerApplications() { return $this->hasMany(SellerApplication::class); }
    public function sellerProfile() { return $this->hasOne(SellerProfile::class); }
    public function store() { return $this->hasOne(Store::class); }
    public function riderProfile() { return $this->hasOne(RiderProfile::class); }
    public function assignedShipments() { return $this->hasMany(Shipment::class, 'rider_id'); }
    public function reportedCases() { return $this->hasMany(Report::class, 'reporter_id'); }
    public function violations() { return $this->hasMany(Violation::class, 'seller_id'); }

    public function isSeller(): bool
    {
        return $this->hasRole('seller');
    }

    public function notificationsData()
    {
        return $this->hasMany(NotificationModel::class);
    }

    public function defaultAddress()
    {
        return $this->addresses()->where('is_default', true)->first() ?? $this->addresses()->first();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin']);
    }

    public function isBuyer(): bool
    {
        return $this->hasRole('buyer');
    }

    public function getAvatarUrlAttribute()
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }
}
