<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'package_id',
        'subscribe_payment_id',
        'invoice_number',
        'currency',
        'description',
        'amount',
        'tax',
        'total',
        'status',
        'issued_at',
        'paid_at',
        'exported_pdf_path',
        'is_trial',
        'trial_fingerprint',
        'ip_address',
        'payment_method',
        'billing_period_start',
        'billing_period_end',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
        'billing_period_start' => 'datetime',
        'billing_period_end' => 'datetime',
        'is_trial' => 'boolean',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionPayment()
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscribe_payment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
