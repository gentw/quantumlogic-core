<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Exceptions\InvoiceLockedException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The only columns that may still change after an invoice is locked —
     * payment tracking and delivery state, never commercial content.
     */
    public const MUTABLE_WHEN_LOCKED = [
        'status',
        'amount_paid',
        'amount_due',
        'paid_at',
        'cancelled_at',
        'exported_pdf_path',
        'public_token',
        'public_token_expires_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        // Immutability after issue (§11 UStG): once locked_at is stamped the
        // commercial content is read-only; corrections go through credit
        // notes. getOriginal() so the save that stamps locked_at itself (the
        // issue transition, which also writes the number) still passes.
        static::updating(function (Invoice $invoice) {
            if (! $invoice->getOriginal('locked_at')) {
                return;
            }

            $illegal = array_diff(array_keys($invoice->getDirty()), self::MUTABLE_WHEN_LOCKED);

            if ($illegal !== []) {
                throw InvoiceLockedException::for($invoice, array_values($illegal));
            }
        });
    }

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

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Overdue is derived, never stored: past due with money still owed.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === InvoiceStatus::Sent
            && $this->due_at?->isPast() === true
            && (float) $this->amount_due > 0;
    }

    public function scopeOverdue($query)
    {
        return $query
            ->where('status', InvoiceStatus::Sent->value)
            ->where('due_at', '<', now())
            ->where('amount_due', '>', 0);
    }

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
