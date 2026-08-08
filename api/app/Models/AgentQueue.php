<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentQueue extends Model
{
    use HasFactory;

    protected $table = 'table_agent_queue';

    protected $fillable = ['client_id'];
}
