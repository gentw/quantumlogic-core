<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'agent_id', 'active', 'live_2'
    ];

    // Define a one-to-many relationship with the Message model
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    // Optional: If you want to get the client and agent directly as user relationships
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }
}
