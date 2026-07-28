<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'id';

    // protected $connection = 'sqlsrv';
    // protected $table = 'vAccountDetails';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'surname',
        'email',
        'phone',
        'phone_2',
        'password',
        'role',
        'is_busy',
        'address',
        'city',
        'postal_code',
        'country_code',
        'company_name',
        'vat_id',
        'img',
        'update_request',
        'deactivated',
        'blocked',
        'department',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    /**
     * Get all subscriptions for the user.
     */
    public function subscriptions()
    {
        return $this->hasMany(\App\Models\Subscription::class);
    }

    /**
     * The user's currently entitling subscription (active or trial, not expired).
     *
     * @return \App\Models\Subscription|null
     */
    public function activeSubscription()
    {
        $entitling = array_map(
            fn ($s) => $s->value,
            \App\Enums\SubscriptionState::entitled()
        );

        $sub = $this->subscriptions()
            ->whereIn('state', $entitling)
            ->orderByDesc('end_date')
            ->first();

        return $sub && $sub->isEntitled() ? $sub : null;
    }

    public function hasFeature(string $feature): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription
            && $subscription->package
            && ! empty($subscription->package->features[$feature]);
    }

    public function features(): array
    {
        $subscription = $this->activeSubscription();

        return $subscription?->package?->features ?? [];
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function serviceOrders()
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function recurringPlans()
    {
        return $this->hasMany(RecurringPlan::class);
    }
}
