<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'uid';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'name',
        'password',
        'password_old',
        'mail',
        'status',
        'new_pass_set'
    ];

    protected $hidden = [
        'password',
        'password_old',
        'remember_token',
    ];

    protected $casts = [
        'status' => 'integer',
        'new_pass_set' => 'integer',
    ];

    // Accessor: Allow reading 'email' which maps to 'mail' column
    public function getEmailAttribute()
    {
        return $this->attributes['mail'] ?? null;
    }

    // Mutator: Allow setting 'email' which stores in 'mail' column
    public function setEmailAttribute($value)
    {
        $this->attributes['mail'] = $value;
    }

    public function getAuthIdentifierName()
    {
        return 'name';
    }

    // Relationships
    public function roles()
    {
        // Using user_role pivot table
        // uid in user_role references uid in users
        // rid in user_role references id in roles
        return $this->belongsToMany(Role::class, 'user_role', 'uid', 'rid');
    }

    public function passwordHistories()
    {
        return $this->hasMany(PasswordHistory::class, 'uid', 'uid');
    }

    public function errorLogs()
    {
        return $this->hasMany(ErrorLog::class, 'user_id', 'uid');
    }

    // Helper methods
    public function hasRole($roleName)
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function hasPermission($permissionName)
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }

    // Note: Password hashing is handled in controllers to allow password history tracking
}
