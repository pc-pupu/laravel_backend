<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $table = 'error_logs';
    public $timestamps = true;

    protected $fillable = [
        'level',
        'message',
        'context',
        'file',
        'line',
        'trace',
        'user_id',
        'url',
        'method',
        'ip_address',
    ];

    protected $casts = [
        'context' => 'array',
        'trace' => 'array',
        'line' => 'integer',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uid');
    }
    
    // Accessor to safely get user name
    public function getUserNameAttribute()
    {
        return $this->user ? $this->user->name : 'System';
    }
}
