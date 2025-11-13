<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordHistory extends Model
{
    protected $table = 'password_history';
    public $timestamps = true;

    protected $fillable = [
        'uid',
        'password_hash',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }
}
