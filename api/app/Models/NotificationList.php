<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 
        'recipient_user_id', 
        'alarm_alert_id',
        'chat_id',
        'type',
        'message',
        'read'
    ];


    public function alarm()
    {
        return $this->belongsTo(IncomingAlarm::class, 'alarm_alert_id');
    }
}
