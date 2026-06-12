<?php

namespace App\Models\CustomerRelations;

use App\Models\Ecommerce\Cart;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Pos\Sale;
use App\Notifications\Customer\VerifyEmailNotification;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class Customer extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SerializesDateToAppTimezone;

    protected $fillable = [
        'name',
        'code',
        'phone',
        'address',
        'email',
        'password',
        'city',
        'zip',
        'province',
        'country',
        'status',
        'user_id',
        'note',
        'points',
        'accumulated_points',
        'business_type',
        'tin',
        'image',
        'e_name',
        'e_phone',
        'e_address',
        'is_wholesale',
        'wholesale_approved_at',
        'wholesale_approved_by',
        'credit_limit',
        'credit_balance',
        'credit_term_days',
        'terms_accepted_at',
        'phone_verified_at',
        'email_verified_at',
        'sms_notifications_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'is_wholesale' => 'boolean',
            'wholesale_approved_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'points' => 'decimal:5',
            'accumulated_points' => 'decimal:5',
            'credit_limit' => 'decimal:2',
            'credit_balance' => 'decimal:2',
            'credit_term_days' => 'integer',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function ecommerceOrders(): HasMany
    {
        return $this->hasMany(EcommerceOrder::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CustomerCreditTransaction::class);
    }

    public function getAvailableCreditAttribute(): float
    {
        return (float) $this->credit_limit - (float) $this->credit_balance;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
