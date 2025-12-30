<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HousingSidebarMenu extends Model
{
    protected $table = 'housing_sidebar_menus';
    protected $primaryKey = 'sidebar_menu_id';
    public $timestamps = true;

    protected $fillable = [
        'menu_name',
        'route_name',
        'url',
        'icon_class',
        'parent_id',
        'order_no',
        'is_active',
        'route_params',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_no' => 'integer',
        'route_params' => 'array',
    ];

    /**
     * Get parent menu
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(HousingSidebarMenu::class, 'parent_id', 'sidebar_menu_id');
    }

    /**
     * Get child menus (submenus)
     */
    public function children(): HasMany
    {
        return $this->hasMany(HousingSidebarMenu::class, 'parent_id', 'sidebar_menu_id')
            ->where('is_active', true)
            ->orderBy('order_no');
    }

    /**
     * Get all roles assigned to this menu
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'housing_sidebar_menu_roles',
            'sidebar_menu_id',
            'role_id'
        )->withTimestamps();
    }

    /**
     * Scope to get only active menus
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only parent menus (no parent_id)
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get menus for specific roles
     */
    public function scopeForRoles($query, array $roleIds)
    {
        return $query->whereHas('roles', function ($q) use ($roleIds) {
            $q->whereIn('roles.id', $roleIds);
        });
    }
}

