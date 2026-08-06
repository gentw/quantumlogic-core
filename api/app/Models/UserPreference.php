<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_news_and_updates',
        'email_tips_and_tutorials',
        'email_my_tickets',
        'email_invoices',
        'email_reminders',
        'push_my_tickets',
        'push_my_comments',
        'push_reminders',
        'push_invoices',
        'user_id',
    ];
}
