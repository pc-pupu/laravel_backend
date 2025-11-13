<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'name',
        'guard_name',
        'drupal_role_id',
    ];

    protected $casts = [
        'drupal_role_id' => 'integer',
    ];

    // Relationships
    public function users()
    {
        // Using users_roles pivot table
        // rid in users_roles references id in roles
        // uid in users_roles references uid in users
        return $this->belongsToMany(User::class, 'users_roles', 'rid', 'uid');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }
}
