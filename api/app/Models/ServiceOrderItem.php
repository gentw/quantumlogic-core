<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_order_id',
        'service_id',
        'description',
        'quantity',
        'unit',
        'unit_price_net',
        'discount_percent',
        'vat_rate',
        'line_total_net',
        'line_total_gross',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price_net' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'line_total_net' => 'decimal:2',
        'line_total_gross' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
