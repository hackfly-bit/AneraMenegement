<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

class AttachmentService
{
    /**
     * Allowed file extensions.
     *
     * @var array
     */
    protected $allowedExtensions = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp',
        'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv',
        'txt', 'csv', 'zip', 'rar', 'tar', 'gz'
    ];

    /**
     * Maximum file size in bytes (10MB).
     *
     * @var int
     */
    protected $maxFileSize = 10485760;

    /**
     * Get all attachments with optional filtering and pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllAttachments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Attachment::with(['uploadedBy']);

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get attachment by ID with relationships.
     *
     * @param int $id
     * @return Attachment|null
     */
    public function getAttachmentById(int $id): ?Attachment
    {
        return Attachment::with(['uploadedBy', 'attachable'])->find($id);
    }

    /**
     * Upload a file and create attachment.
     *
     * @param UploadedFile $file
     * @param int $userId
     * @param array $data
     * @return Attachment
     * @throws \Exception
     */
    public function uploadFile(UploadedFile $file, int $userId, array $data = []): Attachment
    {
        DB::beginTransaction();

        try {
            // Validate file
            $this->validateFile($file);

            // Generate unique filename
            $originalFilename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = $this->generateUniqueFilename($originalFilename, $extension);

            // Determine storage path
            $storagePath = $this->getStoragePath($data['type'] ?? 'general');
            $path = $file->storeAs($storagePath, $filename, 'public');

            // Create attachment record
            $attachment = Attachment::create([
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'path' => $path,
                'disk' => 'public',
                'description' => $data['description'] ?? null,
                'uploaded_by' => $userId,
                'attachable_type' => $data['attachable_type'] ?? null,
                'attachable_id' => $data['attachable_id'] ?? null,
            ]);

            DB::commit();

            Log::info('File uploaded successfully', [
                'attachment_id' => $attachment->id,
                'filename' => $originalFilename,
                'size' => $file->getSize(),
                'user_id' => $userId,
            ]);

            return $attachment->fresh(['uploadedBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded file if transaction failed
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Failed to upload file', [
                'error' => $e->getMessage(),
                'filename' => $file->getClientOriginalName(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Upload multiple files.
     *
     * @param array $files
     * @param int $userId
     * @param array $data
     * @return Collection
     * @throws \Exception
     */
    public function uploadMultipleFiles(array $files, int $userId, array $data = []): Collection
    {
        $attachments = collect();

        DB::beginTransaction();

        try {
            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $attachment = $this->uploadFile($file, $userId, $data);
                    $attachments->push($attachment);
                }
            }

            DB::commit();

            Log::info('Multiple files uploaded successfully', [
                'count' => $attachments->count(),
                'user_id' => $userId,
            ]);

            return $attachments;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to upload multiple files', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Update attachment metadata.
     *
     * @param int $id
     * @param array $data
     * @return Attachment
     * @throws \Exception
     */
    public function updateAttachment(int $id, array $data): Attachment
    {
        $attachment = Attachment::findOrFail($id);

        DB::beginTransaction();

        try {
            $attachment->update([
                'description' => $data['description'] ?? $attachment->description,
                'attachable_type' => $data['attachable_type'] ?? $attachment->attachable_type,
                'attachable_id' => $data['attachable_id'] ?? $attachment->attachable_id,
            ]);

            DB::commit();

            Log::info('Attachment updated successfully', [
                'attachment_id' => $attachment->id,
                'filename' => $attachment->original_filename,
            ]);

            return $attachment->fresh(['uploadedBy', 'attachable']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update attachment', [
                'attachment_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete attachment and file.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteAttachment(int $id): bool
    {
        $attachment = Attachment::findOrFail($id);

        DB::beginTransaction();

        try {
            // Delete physical file
            if ($attachment->fileExists()) {
                $attachment->deleteFile();
            }

            // Delete database record
            $attachment->delete();

            DB::commit();

            Log::info('Attachment deleted successfully', [
                'attachment_id' => $id,
                'filename' => $attachment->original_filename,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete attachment', [
                'attachment_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get attachments by type.
     *
     * @param string $type
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAttachmentsByType(string $type, int $perPage = 15): LengthAwarePaginator
    {
        return Attachment::byType($type)->with(['uploadedBy'])->paginate($perPage);
    }

    /**
     * Get attachments by MIME type.
     *
     * @param string $mimeType
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAttachmentsByMimeType(string $mimeType, int $perPage = 15): LengthAwarePaginator
    {
        return Attachment::byMimeType($mimeType)->with(['uploadedBy'])->paginate($perPage);
    }

    /**
     * Get image attachments.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getImageAttachments(int $perPage = 15): LengthAwarePaginator
    {
        return Attachment::images()->with(['uploadedBy'])->paginate($perPage);
    }

    /**
     * Get document attachments.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getDocumentAttachments(int $perPage = 15): LengthAwarePaginator
    {
        return Attachment::documents()->with(['uploadedBy'])->paginate($perPage);
    }

    /**
     * Get attachments by uploader.
     *
     * @param int $userId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAttachmentsByUploader(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Attachment::byUploader($userId)->with(['uploadedBy'])->paginate($perPage);
    }

    /**
     * Get attachments by attachable.
     *
     * @param string $attachableType
     * @param int $attachableId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAttachmentsByAttachable(string $attachableType, int $attachableId, int $perPage = 15): LengthAwarePaginator
    {
        return Attachment::where('attachable_type', $attachableType)
            ->where('attachable_id', $attachableId)
            ->with(['uploadedBy'])
            ->paginate($perPage);
    }

    /**
     * Search attachments.
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchAttachments(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return Attachment::where('original_filename', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%")
            ->orWhere('mime_type', 'like', "%{$search}%")
            ->with(['uploadedBy'])
            ->paginate($perPage);
    }

    /**
     * Get attachment statistics.
     *
     * @param int $userId
     * @return array
     */
    public function getAttachmentStatistics(int $userId = null): array
    {
        $query = Attachment::query();

        if ($userId) {
            $query->where('uploaded_by', $userId);
        }

        $totalAttachments = $query->count();
        $totalSize = $query->sum('size');

        $byType = $query->select('mime_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(size) as total_size'))
            ->groupBy('mime_type')
            ->get()
            ->map(function ($item) {
                return [
                    'mime_type' => $item->mime_type,
                    'count' => $item->count,
                    'total_size' => $item->total_size,
                    'human_readable_size' => $this->formatBytes($item->total_size),
                ];
            });

        $byUploader = $query->select('uploaded_by', DB::raw('COUNT(*) as count'), DB::raw('SUM(size) as total_size'))
            ->groupBy('uploaded_by')
            ->get()
            ->map(function ($item) {
                return [
                    'uploader_id' => $item->uploaded_by,
                    'count' => $item->count,
                    'total_size' => $item->total_size,
                    'human_readable_size' => $this->formatBytes($item->total_size),
                ];
            });

        $recentAttachments = $query->latest()->limit(10)->get();

        return [
            'total_attachments' => $totalAttachments,
            'total_size' => $totalSize,
            'human_readable_total_size' => $this->formatBytes($totalSize),
            'by_mime_type' => $byType,
            'by_uploader' => $byUploader,
            'recent_attachments' => $recentAttachments,
            'average_size' => $totalAttachments > 0 ? $totalSize / $totalAttachments : 0,
        ];
    }

    /**
     * Bulk delete attachments.
     *
     * @param array $ids
     * @return int
     * @throws \Exception
     */
    public function bulkDeleteAttachments(array $ids): int
    {
        DB::beginTransaction();

        try {
            $attachments = Attachment::whereIn('id', $ids)->get();
            $count = 0;

            foreach ($attachments as $attachment) {
                if ($attachment->fileExists()) {
                    $attachment->deleteFile();
                }
                $attachment->delete();
                $count++;
            }

            DB::commit();

            Log::info('Bulk attachments deleted successfully', [
                'count' => $count,
                'ids' => $ids,
            ]);

            return $count;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk delete attachments', [
                'ids' => $ids,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Clean up orphaned files.
     *
     * @return int
     */
    public function cleanupOrphanedFiles(): int
    {
        $count = 0;
        $storage = Storage::disk('public');
        $attachmentPaths = Attachment::pluck('path')->toArray();

        // Get all files in attachments directory
        $files = $storage->allFiles('attachments');

        foreach ($files as $file) {
            if (!in_array($file, $attachmentPaths)) {
                $storage->delete($file);
                $count++;
            }
        }

        Log::info('Orphaned files cleaned up', ['count' => $count]);
        return $count;
    }

    /**
     * Validate uploaded file.
     *
     * @param UploadedFile $file
     * @throws \Exception
     */
    protected function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new \Exception('File size exceeds maximum allowed size of 10MB');
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new \Exception('File type not allowed');
        }

        // Validate MIME type
        $validator = Validator::make(
            ['file' => $file],
            ['file' => 'mimes:' . implode(',', $this->allowedExtensions)]
        );

        if ($validator->fails()) {
            throw new \Exception('Invalid file type');
        }
    }

    /**
     * Generate unique filename.
     *
     * @param string $originalFilename
     * @param string $extension
     * @return string
     */
    protected function generateUniqueFilename(string $originalFilename, string $extension): string
    {
        $timestamp = Carbon::now()->format('YmdHis');
        $randomString = str_random(8);
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $baseName);
        
        return "{$baseName}_{$timestamp}_{$randomString}.{$extension}";
    }

    /**
     * Get storage path based on type.
     *
     * @param string $type
     * @return string
     */
    protected function getStoragePath(string $type): string
    {
        $date = Carbon::now();
        $year = $date->format('Y');
        $month = $date->format('m');

        return "attachments/{$type}/{$year}/{$month}";
    }

    /**
     * Format bytes to human readable format.
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}