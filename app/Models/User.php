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
        'email',
        'status',
        'new_pass_set'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'status' => 'integer',
        'new_pass_set' => 'integer',
    ];

    public function getAuthIdentifierName()
    {
        return 'name';
    }

    // Relationships
    public function roles()
    {
        // Using users_roles pivot table
        // uid in users_roles references uid in users
        // rid in users_roles references id in roles
        return $this->belongsToMany(Role::class, 'users_roles', 'uid', 'rid');
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
