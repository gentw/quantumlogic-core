<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
        'verification_token',
        'verification_method',
        'verified_at',
        'status',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    // Generate a new verification token
    public static function generateToken(): string
    {
        return 'sg_'.Str::random(32);
    }

    // Relationship: Domain belongs to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Check if domain is verified
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
