<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'department',
        'position',
        'is_active',
        'last_login_at',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Validation rules for the model.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:50',
            'avatar' => 'nullable|string|max:500',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Validation rules for updating the model.
     *
     * @param int|null $userId
     * @return array<string, string>
     */
    public static function updateRules(?int $userId = null): array
    {
        $rules = self::rules();
        
        // Allow same email for current user
        if ($userId) {
            $rules['email'] = 'required|email|unique:users,email,' . $userId . '|max:255';
            $rules['password'] = 'nullable|string|min:8';
        }
        
        return $rules;
    }

    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withTimestamps();
    }

    /**
     * Get the content created by the user.
     */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'author_id');
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the attachments uploaded by the user.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'uploaded_by');
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive users.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Check if the user is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if the user is inactive.
     */
    public function isInactive(): bool
    {
        return $this->is_active === false;
    }

    /**
     * Activate the user.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the user.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    /**
     * Check if the user has all of the given roles.
     */
    public function hasAllRoles(array $roleNames): bool
    {
        $userRoles = $this->roles()->pluck('name')->toArray();
        return count(array_diff($roleNames, $userRoles)) === 0;
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permissionName) {
            $query->where('name', $permissionName);
        })->exists();
    }

    /**
     * Check if the user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissionNames): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permissionNames) {
            $query->whereIn('name', $permissionNames);
        })->exists();
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = \App\Models\Role::where('name', $role)->firstOrFail();
        }
        $this->roles()->attach($role->id);
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(Role $role): void
    {
        $this->roles()->detach($role->id);
    }

    /**
     * Sync roles for the user.
     */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync($roleIds);
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get the user's avatar URL or default avatar.
     */
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=random';
    }

    /**
     * Get the user's role names.
     */
    public function getRoleNamesAttribute(): array
    {
        return $this->roles()->pluck('name')->toArray();
    }

    /**
     * Get the user's unread notifications count.
     */
    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->notifications()->unread()->count();
    }

    /**
     * Get the user's last activity.
     */
    public function getLastActivityAttribute(): ?string
    {
        return $this->last_login_at ? $this->last_login_at->diffForHumans() : null;
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Get full user information.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url,
            'department' => $this->department,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'last_activity' => $this->last_activity,
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'role_names' => $this->role_names,
            'roles_count' => $this->roles()->count(),
            'unread_notifications_count' => $this->unread_notifications_count,
            'contents_count' => $this->contents()->count(),
            'attachments_count' => $this->attachments()->count(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
