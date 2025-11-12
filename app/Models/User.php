<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users'; // your existing table
    protected $primaryKey = 'uid'; // your actual PK
    public $timestamps = true; // if no created_at/updated_at

    protected $fillable = [
        'uid',
        'name',
        'password',
        'email',
        'status',
        'created_at',
        'updated_at',
        'new_pass_set'
    ];

    public function getAuthIdentifierName()
    {
        return 'username';
    }
}
