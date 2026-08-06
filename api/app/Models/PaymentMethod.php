<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_token',
        'provider_customer_id',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'is_default',
        'verified_at',
    ];

    /** Provider references never leave the backend. */
    protected $hidden = [
        'provider_token',
        'provider_customer_id',
    ];

    protected $casts = [
        'provider' => PaymentProvider::class,
        'exp_month' => 'integer',
        'exp_year' => 'integer',
        'is_default' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
