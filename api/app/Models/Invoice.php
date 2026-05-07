<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['subscription_id', 'currency', 'status', 'description', 'invoice_number','amount','tax','total','status','issued_at','paid_at','exported_pdf_path', 'subscribe_payment_id',
'is_trial', 'trial_fingerprint', 'ip_address', 'package_id'


];
    
    public function subscription() { 
        return $this->belongsTo(Subscription::class); 
    }

    public function subscriptionPayment() { 
        return $this->belongsTo(SubscriptionPayment::class, 'subscribe_payment_id'); 
    }
}
