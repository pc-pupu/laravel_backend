<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';
    // Database primary key column is now 'rid' instead of 'id'
    protected $primaryKey = 'rid';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'name',
        'guard_name',
        'drupal_role_id',
        'is_active'
    ];

    protected $casts = [
        'drupal_role_id' => 'integer',
        'is_active' => 'integer',
    ];

    // Relationships
    public function users()
    {
        // Using user_role pivot table
        // rid in user_role references id in roles
        // uid in user_role references uid in users
        return $this->belongsToMany(User::class, 'user_role', 'rid', 'uid');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }
}
