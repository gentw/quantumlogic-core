<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient_id',
        'user_id',
        'subject',
        'type',
        'priority',
        'delivery_schedule',
        'execute_time',
        'system',
        'notif_reminder_group_id',
    ];

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
