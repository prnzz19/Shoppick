<?php

namespace App\Models;

use App\Support\BirthdayAge;
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
        'first_name', 'middle_initial', 'last_name', 'sex', 'birthday',
        'email',
        'phone',
        'avatar',
        'valid_id_path', 'registration_type', 'registration_status', 'registration_review_notes',
        'registration_reviewed_by', 'registration_reviewed_at',
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
        'valid_id_path',
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
            'birthday' => 'date',
            'registration_reviewed_at' => 'datetime',
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
    public function riderMessages() { return $this->hasMany(RiderMessage::class, 'rider_id'); }
    public function assignedShipments() { return $this->hasMany(Shipment::class, 'rider_id'); }
    public function reportedCases() { return $this->hasMany(Report::class, 'reporter_id'); }
    public function violations() { return $this->hasMany(Violation::class, 'seller_id'); }

    public function isSeller(): bool
    {
        return $this->hasRole('seller');
    }

    public function hasApprovedSellerAccess(): bool
    {
        return $this->is_active && $this->hasRole('seller')
            && $this->sellerProfile()->where('status', 'approved')->exists()
            && $this->store()->where('status', 'active')->exists();
    }

    public function sellerAction(): array
    {
        if ($this->hasApprovedSellerAccess()) return ['label' => 'Seller Dashboard', 'url' => route('seller.dashboard'), 'description' => 'Manage your shop while keeping your Buyer account.'];
        $application = $this->sellerApplications()->latest('id')->first();
        $label = match ($application?->status) {
            'pending', 'escalated', 'awaiting_final_review' => 'View Application Status',
            'needs_resubmission' => 'Update Application',
            'rejected' => 'View Application Result',
            'approved' => 'View Seller Status',
            default => 'Become a Seller',
        };
        return ['label' => $label, 'url' => route('seller.apply'), 'description' => $application?->review_notes ?: ($application ? 'Check your seller application and next steps.' : 'Start selling your products using your existing Buyer account.')];
    }

    public function notificationsData()
    {
        return $this->hasMany(NotificationModel::class);
    }

    public function defaultAddress()
    {
        return $this->addresses()->where('is_default', true)->first() ?? $this->addresses()->first();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isBuyer(): bool
    {
        return $this->hasRole('buyer');
    }

    public function getAgeAttribute(): ?int
    {
        return BirthdayAge::calculate($this->birthday);
    }

    public function registrationReviewer() { return $this->belongsTo(User::class, 'registration_reviewed_by'); }

    public function getAvatarUrlAttribute()
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }
}
