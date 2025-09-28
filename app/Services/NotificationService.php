<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Get all notifications with optional filtering and pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllNotifications(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Notification::with(['user']);

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get notification by ID with relationships.
     *
     * @param int $id
     * @return Notification|null
     */
    public function getNotificationById(int $id): ?Notification
    {
        return Notification::with(['user'])->find($id);
    }

    /**
     * Get user notifications.
     *
     * @param int $userId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserNotifications(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Notification::where('user_id', $userId)->with(['user']);

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get unread user notifications.
     *
     * @param int $userId
     * @param int $limit
     * @return Collection
     */
    public function getUnreadUserNotifications(int $userId, int $limit = 10): Collection
    {
        return Notification::where('user_id', $userId)
            ->unread()
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create a new notification.
     *
     * @param array $data
     * @return Notification
     * @throws \Exception
     */
    public function createNotification(array $data): Notification
    {
        DB::beginTransaction();

        try {
            // Validate user exists
            $user = User::findOrFail($data['user_id']);

            $notification = Notification::create([
                'user_id' => $data['user_id'],
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'data' => $data['data'] ?? null,
                'priority' => $data['priority'] ?? 'medium',
                'action_url' => $data['action_url'] ?? null,
                'action_text' => $data['action_text'] ?? null,
                'notifiable_type' => $data['notifiable_type'] ?? null,
                'notifiable_id' => $data['notifiable_id'] ?? null,
            ]);

            DB::commit();

            Log::info('Notification created successfully', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type,
            ]);

            return $notification->fresh(['user']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create notification', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Mark notification as read.
     *
     * @param int $id
     * @return Notification
     * @throws \Exception
     */
    public function markAsRead(int $id): Notification
    {
        $notification = Notification::findOrFail($id);

        if ($notification->is_read) {
            return $notification;
        }

        DB::beginTransaction();

        try {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            DB::commit();

            Log::info('Notification marked as read', ['notification_id' => $id]);

            return $notification->fresh(['user']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark notification as read', ['notification_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Mark notification as unread.
     *
     * @param int $id
     * @return Notification
     * @throws \Exception
     */
    public function markAsUnread(int $id): Notification
    {
        $notification = Notification::findOrFail($id);

        if (!$notification->is_read) {
            return $notification;
        }

        DB::beginTransaction();

        try {
            $notification->update([
                'is_read' => false,
                'read_at' => null,
            ]);

            DB::commit();

            Log::info('Notification marked as unread', ['notification_id' => $id]);

            return $notification->fresh(['user']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark notification as unread', ['notification_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Mark all user notifications as read.
     *
     * @param int $userId
     * @return int
     * @throws \Exception
     */
    public function markAllAsRead(int $userId): int
    {
        DB::beginTransaction();

        try {
            $count = Notification::where('user_id', $userId)
                ->unread()
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            DB::commit();

            Log::info('All user notifications marked as read', ['user_id' => $userId, 'count' => $count]);

            return $count;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark all user notifications as read', ['user_id' => $userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete a notification.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteNotification(int $id): bool
    {
        $notification = Notification::findOrFail($id);

        DB::beginTransaction();

        try {
            $notification->delete();

            DB::commit();

            Log::info('Notification deleted successfully', ['notification_id' => $id]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete notification', ['notification_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete old notifications.
     *
     * @param int $days
     * @return int
     * @throws \Exception
     */
    public function deleteOldNotifications(int $days = 30): int
    {
        $cutoffDate = Carbon::now()->subDays($days);

        DB::beginTransaction();

        try {
            $count = Notification::where('created_at', '<', $cutoffDate)->delete();

            DB::commit();

            Log::info('Old notifications deleted', ['days' => $days, 'count' => $count]);

            return $count;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete old notifications', ['days' => $days, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Send notification to user.
     *
     * @param int $userId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array $data
     * @param string $priority
     * @param string|null $actionUrl
     * @param string|null $actionText
     * @return Notification
     * @throws \Exception
     */
    public function sendNotificationToUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        string $priority = 'medium',
        ?string $actionUrl = null,
        ?string $actionText = null
    ): Notification {
        return $this->createNotification([
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
     * Send system notification.
     *
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array $data
     * @param string $priority
     * @param string|null $actionUrl
     * @param string|null $actionText
     * @return Collection
     * @throws \Exception
     */
    public function sendSystemNotification(
        string $type,
        string $title,
        string $message,
        array $data = [],
        string $priority = 'medium',
        ?string $actionUrl = null,
        ?string $actionText = null
    ): Collection {
        $users = User::active()->get();
        $notifications = collect();

        DB::beginTransaction();

        try {
            foreach ($users as $user) {
                $notification = $this->createNotification([
                    'user_id' => $user->id,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                    'priority' => $priority,
                    'action_url' => $actionUrl,
                    'action_text' => $actionText,
                ]);

                $notifications->push($notification);
            }

            DB::commit();

            Log::info('System notification sent', [
                'type' => $type,
                'title' => $title,
                'user_count' => $notifications->count(),
            ]);

            return $notifications;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send system notification', [
                'type' => $type,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Notify invoice created.
     *
     * @param Invoice $invoice
     * @return Notification
     */
    public function notifyInvoiceCreated(Invoice $invoice): Notification
    {
        return $this->sendNotificationToUser(
            $invoice->client->id,
            'invoice_created',
            "Invoice {$invoice->invoice_number} Created",
            "Your invoice {$invoice->invoice_number} for {$invoice->total} has been created.",
            [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->total,
                'due_date' => $invoice->due_date->format('Y-m-d'),
            ],
            'medium',
            "/invoices/{$invoice->id}",
            'View Invoice'
        );
    }

    /**
     * Notify payment received.
     *
     * @param Payment $payment
     * @return Notification
     */
    public function notifyPaymentReceived(Payment $payment): Notification
    {
        return $this->sendNotificationToUser(
            $payment->invoice->client->id,
            'payment_received',
            'Payment Received',
            "Payment of {$payment->amount} has been received for invoice {$payment->invoice->invoice_number}.",
            [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
            ],
            'medium',
            "/invoices/{$payment->invoice_id}",
            'View Invoice'
        );
    }

    /**
     * Notify project status change.
     *
     * @param Project $project
     * @param string $oldStatus
     * @return Notification
     */
    public function notifyProjectStatusChange(Project $project, string $oldStatus): Notification
    {
        return $this->sendNotificationToUser(
            $project->client->id,
            'project_status_changed',
            'Project Status Updated',
            "Your project '{$project->name}' status has been changed from {$oldStatus} to {$project->status}.",
            [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'old_status' => $oldStatus,
                'new_status' => $project->status,
            ],
            'medium',
            "/projects/{$project->id}",
            'View Project'
        );
    }

    /**
     * Notify overdue invoice.
     *
     * @param Invoice $invoice
     * @return Notification
     */
    public function notifyOverdueInvoice(Invoice $invoice): Notification
    {
        return $this->sendNotificationToUser(
            $invoice->client->id,
            'invoice_overdue',
            'Invoice Overdue',
            "Your invoice {$invoice->invoice_number} for {$invoice->total} is now overdue.",
            [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->total,
                'overdue_days' => $invoice->due_date->diffInDays(now()),
            ],
            'high',
            "/invoices/{$invoice->id}",
            'Pay Now'
        );
    }

    /**
     * Notify upcoming payment due.
     *
     * @param Invoice $invoice
     * @return Notification
     */
    public function notifyUpcomingPaymentDue(Invoice $invoice): Notification
    {
        $daysUntilDue = now()->diffInDays($invoice->due_date);

        return $this->sendNotificationToUser(
            $invoice->client->id,
            'payment_due_soon',
            'Payment Due Soon',
            "Your invoice {$invoice->invoice_number} for {$invoice->total} is due in {$daysUntilDue} days.",
            [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->total,
                'days_until_due' => $daysUntilDue,
                'due_date' => $invoice->due_date->format('Y-m-d'),
            ],
            'medium',
            "/invoices/{$invoice->id}",
            'View Invoice'
        );
    }

    /**
     * Notify new client registration.
     *
     * @param Client $client
     * @return Notification
     */
    public function notifyNewClientRegistration(Client $client): Notification
    {
        return $this->sendSystemNotification(
            'new_client_registered',
            'New Client Registered',
            "New client {$client->name} has been registered.",
            [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'client_email' => $client->email,
            ],
            'low',
            "/clients/{$client->id}",
            'View Client'
        )->first();
    }

    /**
     * Notify project completion.
     *
     * @param Project $project
     * @return Notification
     */
    public function notifyProjectCompletion(Project $project): Notification
    {
        return $this->sendNotificationToUser(
            $project->client->id,
            'project_completed',
            'Project Completed',
            "Your project '{$project->name}' has been completed successfully.",
            [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'completion_date' => now()->format('Y-m-d'),
            ],
            'medium',
            "/projects/{$project->id}",
            'View Project'
        );
    }

    /**
     * Get notification statistics.
     *
     * @param int $userId
     * @return array
     */
    public function getNotificationStatistics(int $userId): array
    {
        $totalNotifications = Notification::where('user_id', $userId)->count();
        $unreadNotifications = Notification::where('user_id', $userId)->unread()->count();
        $readNotifications = Notification::where('user_id', $userId)->read()->count();

        $notificationsByType = Notification::where('user_id', $userId)
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->type,
                    'count' => $item->count,
                ];
            });

        $notificationsByPriority = Notification::where('user_id', $userId)
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->get()
            ->map(function ($item) {
                return [
                    'priority' => $item->priority,
                    'count' => $item->count,
                ];
            });

        return [
            'total' => $totalNotifications,
            'unread' => $unreadNotifications,
            'read' => $readNotifications,
            'unread_percentage' => $totalNotifications > 0 ? round(($unreadNotifications / $totalNotifications) * 100, 2) : 0,
            'by_type' => $notificationsByType,
            'by_priority' => $notificationsByPriority,
        ];
    }

    /**
     * Apply filters to notification query.
     *
     * @param Builder $query
     * @param array $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        // Type filter
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Priority filter
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // Status filter
        if (isset($filters['is_read'])) {
            if ($filters['is_read']) {
                $query->read();
            } else {
                $query->unread();
            }
        }

        // Date range filter
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('message', 'like', '%' . $filters['search'] . '%');
            });
        }
    }
}