<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'provider',
        'provider_payment_id',
        'provider_customer_id',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'method_brand',
        'method_last4',
        'paid_at',
        'failure_reason',
        'confirmed_by_admin_id',
        'confirmed_at',
        'refunded_amount',
        'metadata',
    ];

    protected $casts = [
        'provider' => PaymentProvider::class,
        'status' => PaymentStatus::class,
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function proofs()
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function confirmedByAdmin()
    {
        return $this->belongsTo(User::class, 'confirmed_by_admin_id');
    }
}
