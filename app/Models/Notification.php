<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
        'notifiable_type',
        'notifiable_id',
        'action_url',
        'action_text',
        'priority',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
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
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'data' => 'nullable|array',
            'is_read' => 'boolean',
            'read_at' => 'nullable|date',
            'notifiable_type' => 'nullable|string|max:100',
            'notifiable_id' => 'nullable|integer',
            'action_url' => 'nullable|string|max:500',
            'action_text' => 'nullable|string|max:100',
            'priority' => 'required|in:low,medium,high,critical',
        ];
    }

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent notifiable model.
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to only include read notifications.
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope a query to only include notifications by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include notifications by priority.
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to only include high priority notifications.
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    /**
     * Scope a query to only include critical priority notifications.
     */
    public function scopeCriticalPriority($query)
    {
        return $query->where('priority', 'critical');
    }

    /**
     * Scope a query to only include recent notifications.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Check if the notification is unread.
     */
    public function isUnread(): bool
    {
        return $this->is_read === false;
    }

    /**
     * Check if the notification is read.
     */
    public function isRead(): bool
    {
        return $this->is_read === true;
    }

    /**
     * Check if the notification is critical.
     */
    public function isCritical(): bool
    {
        return $this->priority === 'critical';
    }

    /**
     * Check if the notification is high priority.
     */
    public function isHighPriority(): bool
    {
        return $this->priority === 'high';
    }

    /**
     * Check if the notification has an action.
     */
    public function hasAction(): bool
    {
        return !empty($this->action_url) && !empty($this->action_text);
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark the notification as unread.
     */
    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Get the priority display name.
     */
    public function getPriorityDisplayAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
            default => ucfirst($this->priority),
        };
    }

    /**
     * Get the priority color class.
     */
    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'bg-blue-100 text-blue-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'high' => 'bg-orange-100 text-orange-800',
            'critical' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get the time ago display.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the formatted created date.
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->created_at->format('M j, Y g:i A');
    }

    /**
     * Check if the notification is recent.
     */
    public function isRecent(int $hours = 24): bool
    {
        return $this->created_at->greaterThan(now()->subHours($hours));
    }

    /**
     * Get notification data value.
     */
    public function getDataValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Set notification data value.
     */
    public function setDataValue(string $key, mixed $value): void
    {
        $data = $this->data ?? [];
        data_set($data, $key, $value);
        $this->update(['data' => $data]);
    }

    /**
     * Create a notification for a user.
     */
    public static function createForUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        string $priority = 'medium',
        string $actionUrl = null,
        string $actionText = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
            'action_url' => $actionUrl,
            'action_text' => $actionText,
        ]);
    }

    /**
     * Create a system notification.
     */
    public static function createSystemNotification(
        string $type,
        string $title,
        string $message,
        array $data = [],
        string $priority = 'medium'
    ): self {
        // For system notifications, we might want to notify all admins
        // or a specific system user. For now, we'll create it without a user.
        return self::create([
            'user_id' => 1, // System user or admin
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
        ]);
    }

    /**
     * Get unread count for a user.
     */
    public static function getUnreadCountForUser(int $userId): int
    {
        return self::where('user_id', $userId)
                  ->unread()
                  ->count();
    }

    /**
     * Mark all notifications as read for a user.
     */
    public static function markAllAsReadForUser(int $userId): int
    {
        return self::where('user_id', $userId)
                  ->unread()
                  ->update([
                      'is_read' => true,
                      'read_at' => now(),
                  ]);
    }

    /**
     * Delete old notifications.
     */
    public static function deleteOldNotifications(int $days = 30): int
    {
        return self::where('created_at', '<', now()->subDays($days))
                  ->where('is_read', true)
                  ->delete();
    }

    /**
     * Get full notification information.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'priority' => $this->priority,
            'priority_display' => $this->priority_display,
            'priority_color' => $this->priority_color,
            'time_ago' => $this->time_ago,
            'formatted_date' => $this->formatted_date,
            'is_recent' => $this->isRecent(),
            'has_action' => $this->hasAction(),
            'action_url' => $this->action_url,
            'action_text' => $this->action_text,
            'notifiable_type' => $this->notifiable_type,
            'notifiable_id' => $this->notifiable_id,
            'user' => $this->user,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($notification) {
            // Set default priority if not provided
            if (empty($notification->priority)) {
                $notification->priority = 'medium';
            }
            
            // Set default unread status
            if (is_null($notification->is_read)) {
                $notification->is_read = false;
            }
        });
    }
}