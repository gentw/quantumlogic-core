<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomingAlarm extends Model
{
    use HasFactory;

    protected $table = 'alarm_incomings';

    protected $fillable = [
        'signal_id',
        'user_id',
        'signal_string',
        'alarm_description',
        'acc_name',
        'is_me',
        'account_number',
        'is_triggered',
        'is_closed',
        'status',
        'base_notified',
    ];

    public function alarm_logs()
    {
        return $this->hasMany(AlarmIncomingLog::class, 'alarm_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
