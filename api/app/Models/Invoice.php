<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'package_id',
        'subscribe_payment_id',
        'service_order_id',
        'type',
        'parent_invoice_id',
        'account_manager_id',
        'invoice_number',
        'currency',
        'description',
        'reference',
        'terms',
        'notes',
        'amount',
        'tax',
        'total',
        'subtotal_net',
        'discount_total',
        'vat_total',
        'vat_rate',
        'total_gross',
        'amount_paid',
        'amount_due',
        'reverse_charge',
        'status',
        'issued_at',
        'due_at',
        'sent_at',
        'paid_at',
        'cancelled_at',
        'locked_at',
        'public_token',
        'public_token_expires_at',
        'exported_pdf_path',
        'is_trial',
        'trial_fingerprint',
        'ip_address',
        'payment_method',
        'billing_period_start',
        'billing_period_end',
    ];

    protected $casts = [
        // type is null on legacy plan-tier rows.
        'type' => InvoiceType::class,
        'status' => InvoiceStatus::class,
        'subtotal_net' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'vat_total' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'reverse_charge' => 'boolean',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'locked_at' => 'datetime',
        'public_token_expires_at' => 'datetime',
        'billing_period_start' => 'datetime',
        'billing_period_end' => 'datetime',
        'is_trial' => 'boolean',
    ];

    /** Public tokens never serialize by accident. */
    protected $hidden = [
        'public_token',
        'trial_fingerprint',
        'ip_address',
    ];

    public function serviceOrder()
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function activities()
    {
        return $this->hasMany(InvoiceActivity::class)->orderBy('id');
    }

    public function reminders()
    {
        return $this->hasMany(InvoiceReminder::class);
    }

    /** The invoice a credit note corrects. */
    public function parentInvoice()
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    public function creditNotes()
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Retired plan-tier module relations — kept for historical rows.

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionPayment()
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscribe_payment_id');
    }
}
