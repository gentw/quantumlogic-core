<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail. Rows are written once and never updated — there
 * is no updated_at column, and nothing should ever call save() on one.
 */
class InvoiceActivity extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'actor_user_id',
        'event',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Null actor = the system (scheduler, webhook). */
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
