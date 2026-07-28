<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Backs gapless invoice numbering. Rows are only ever touched inside
 * InvoiceNumberService under SELECT ... FOR UPDATE — do not increment
 * last_number anywhere else.
 */
class InvoiceSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'prefix',
        'year',
        'last_number',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_number' => 'integer',
    ];
}
