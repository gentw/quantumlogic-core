<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id', 'user_id', 'message'
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    // Optional: Relationship to get the user who sent the message
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
