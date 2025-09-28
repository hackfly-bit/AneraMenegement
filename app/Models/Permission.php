<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
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
        'module',
        'action',
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
            'name' => 'required|string|max:100|unique:permissions,name',
            'description' => 'nullable|string|max:500',
            'module' => 'required|string|max:50',
            'action' => 'required|string|max:50',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The roles that belong to the permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
                    ->withTimestamps();
    }

    /**
     * The users that belong to the permission through roles.
     */
    public function users()
    {
        return $this->roles()->with('users')->get()->pluck('users')->flatten()->unique('id');
    }

    /**
     * Scope a query to only include active permissions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include permissions by module.
     */
    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope a query to only include permissions by action.
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Check if the permission is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Activate the permission.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the permission.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Check if the permission belongs to a specific module.
     */
    public function belongsToModule(string $module): bool
    {
        return $this->module === $module;
    }

    /**
     * Check if the permission is for a specific action.
     */
    public function isForAction(string $action): bool
    {
        return $this->action === $action;
    }

    /**
     * Get permission by name.
     */
    public static function findByName(string $name): ?self
    {
        return self::where('name', $name)->first();
    }

    /**
     * Get permissions by module.
     */
    public static function getByModule(string $module): \Illuminate\Database\Eloquent\Collection
    {
        return self::byModule($module)->active()->get();
    }

    /**
     * Get all available modules.
     */
    public static function getAvailableModules(): array
    {
        return self::active()
                  ->distinct()
                  ->pluck('module')
                  ->sort()
                  ->values()
                  ->toArray();
    }

    /**
     * Get all available actions for a module.
     */
    public static function getAvailableActions(string $module): array
    {
        return self::active()
                  ->byModule($module)
                  ->distinct()
                  ->pluck('action')
                  ->sort()
                  ->values()
                  ->toArray();
    }

    /**
     * Get permission statistics.
     */
    public function getStatistics(): array
    {
        return [
            'roles_count' => $this->roles()->count(),
            'users_count' => $this->users()->count(),
            'is_active' => $this->isActive(),
        ];
    }

    /**
     * Get full permission information.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'module' => $this->module,
            'action' => $this->action,
            'is_active' => $this->is_active,
            'roles_count' => $this->roles()->count(),
            'users_count' => $this->users()->count(),
            'roles' => $this->roles,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Create default permissions for a module.
     */
    public static function createDefaultPermissions(string $module, array $actions = []): array
    {
        $defaultActions = $actions ?: ['view', 'create', 'update', 'delete'];
        $createdPermissions = [];
        
        foreach ($defaultActions as $action) {
            $permission = self::firstOrCreate([
                'name' => $module . '.' . $action,
            ], [
                'description' => 'Can ' . $action . ' ' . $module,
                'module' => $module,
                'action' => $action,
                'is_active' => true,
            ]);
            
            $createdPermissions[] = $permission;
        }
        
        return $createdPermissions;
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($permission) {
            // Set default active status if not provided
            if (is_null($permission->is_active)) {
                $permission->is_active = true;
            }
            
            // Generate name from module and action if not provided
            if (empty($permission->name)) {
                $permission->name = $permission->module . '.' . $permission->action;
            }
        });

        static::deleting(function ($permission) {
            // Detach all roles when permission is deleted
            $permission->roles()->detach();
        });
    }
}