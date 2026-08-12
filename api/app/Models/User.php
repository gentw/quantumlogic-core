<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements HasLocalePreference
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Laravel reads this whenever a User is the mail or notification recipient,
     * including for queued sends, where the job runs long after the request
     * that created it and `App::getLocale()` would be the queue worker's
     * default. Returning null leaves the app locale in place.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    protected static function booted(): void
    {
        // Staff accounts start with the login code required; clients start
        // without it. Set here rather than at each creation site so it holds for
        // the four signup endpoints, admin-created accounts and guest checkout
        // alike. An explicit value in the create() call still wins.
        static::creating(function (self $user) {
            if ($user->two_factor_enabled === null) {
                $user->two_factor_enabled = in_array(
                    $user->role,
                    config('two_factor.default_on_roles', []),
                    true,
                );
            }
        });
    }

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
        'origin',
        'is_busy',
        'address',
        'city',
        'postal_code',
        'country_code',
        'locale',
        'two_factor_enabled',
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
        'two_factor_enabled' => 'boolean',
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
