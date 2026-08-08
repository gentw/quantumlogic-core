<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlarmIncomingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'alarm_id',
        'event_type',
        'user_id',
        'details',
    ];

    public function alarm()
    {
        return $this->belongsTo(IncomingAlarm::class, 'alarm_id');
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}
