<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
