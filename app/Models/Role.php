<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Validation rules for the model.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The users that belong to the role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
                    ->withTimestamps();
    }

    /**
     * The permissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
                    ->withTimestamps();
    }

    /**
     * Scope a query to only include active roles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if the role is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Activate the role.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the role.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * Assign a permission to the role.
     */
    public function assignPermission(Permission $permission): void
    {
        $this->permissions()->attach($permission->id);
    }

    /**
     * Remove a permission from the role.
     */
    public function removePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }

    /**
     * Sync permissions for the role.
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }

    /**
     * Get all users with this role.
     */
    public function getUsersWithRole()
    {
        return $this->users()->with('roles')->get();
    }

    /**
     * Get role statistics.
     */
    public function getStatistics(): array
    {
        return [
            'users_count' => $this->users()->count(),
            'permissions_count' => $this->permissions()->count(),
            'is_active' => $this->isActive(),
        ];
    }

    /**
     * Get full role information.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'users_count' => $this->users()->count(),
            'permissions_count' => $this->permissions()->count(),
            'permissions' => $this->permissions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($role) {
            // Set default active status if not provided
            if (is_null($role->is_active)) {
                $role->is_active = true;
            }
        });

        static::deleting(function ($role) {
            // Detach all users and permissions when role is deleted
            $role->users()->detach();
            $role->permissions()->detach();
        });
    }
}