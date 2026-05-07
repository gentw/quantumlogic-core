<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationReminderGroup extends Model
{
    use HasFactory;

    protected $fillable = ['subject'];

    public function reminders()
    {
        return $this->hasMany(NotificationReminder::class);
    }
}
