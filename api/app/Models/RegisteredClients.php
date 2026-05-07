<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegisteredClients extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'surname', 'email', 'phone', 'img', 'password'
    ];
}
