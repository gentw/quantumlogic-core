<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        'img',
        'update_request',
        'deactivated',
        'blocked',
        'department'
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
     * Get the user's active subscription.
     *
     * @return \App\Models\Subscription|null
     * Returns the first subscription with 'active' status for the user.
     * If the user has no active subscription, returns null.
     */
    public function activeSubscription()
    {
        return $this->subscription()->where('status', 'active')->first();
    }

    /**
     * Check if the user has access to a specific feature.
     *
     * @param string $feature The feature key to check (e.g., 'AI_logs').
     * @return bool
     * Returns true if the user's active subscription package includes the feature and it's enabled.
     * Returns false if no active subscription exists or the feature is disabled/missing.
     */
    public function hasFeature(string $feature): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription && !empty($subscription->package->features[$feature]) && $subscription->package->features[$feature];
    }

    /**
     * Get all feature flags from the user's active subscription package.
     *
     * @return array
     * Returns an array of features from the package (e.g., ['AI_logs' => true, 'max_origins' => 3]).
     * Returns an empty array if the user has no active subscription.
     */
    public function features(): array
    {
        $subscription = $this->activeSubscription();
        return $subscription->package->features ?? [];
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

}
