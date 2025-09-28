<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'filename',
        'original_filename',
        'mime_type',
        'size',
        'path',
        'disk',
        'description',
        'uploaded_by',
        'attachable_type',
        'attachable_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'size' => 'integer',
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
            'filename' => 'required|string|max:255',
            'original_filename' => 'required|string|max:255',
            'mime_type' => 'required|string|max:100',
            'size' => 'required|integer|min:0',
            'path' => 'required|string|max:500',
            'disk' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
            'uploaded_by' => 'nullable|exists:users,id',
            'attachable_type' => 'nullable|string|max:100',
            'attachable_id' => 'nullable|integer',
        ];
    }

    /**
     * Get the parent attachable model.
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who uploaded the attachment.
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope a query to only include attachments by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('attachable_type', $type);
    }

    /**
     * Scope a query to only include attachments by MIME type.
     */
    public function scopeByMimeType($query, string $mimeType)
    {
        return $query->where('mime_type', 'like', $mimeType . '%');
    }

    /**
     * Scope a query to only include image attachments.
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    /**
     * Scope a query to only include document attachments.
     */
    public function scopeDocuments($query)
    {
        return $query->whereIn('mime_type', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Scope a query to only include attachments by uploader.
     */
    public function scopeByUploader($query, int $userId)
    {
        return $query->where('uploaded_by', $userId);
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the attachment is a document.
     */
    public function isDocument(): bool
    {
        return in_array($this->mime_type, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ]);
    }

    /**
     * Check if the attachment is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Check if the attachment is an audio file.
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    /**
     * Get the file size in human readable format.
     */
    public function getHumanReadableSizeAttribute(): string
    {
        $bytes = $this->size;
        
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log(1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    /**
     * Get the file extension.
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->original_filename, PATHINFO_EXTENSION);
    }

    /**
     * Get the file icon based on MIME type.
     */
    public function getIconAttribute(): string
    {
        if ($this->isImage()) {
            return '🖼️';
        }
        
        if ($this->isDocument()) {
            return '📄';
        }
        
        if ($this->isVideo()) {
            return '🎥';
        }
        
        if ($this->isAudio()) {
            return '🎵';
        }
        
        return '📎';
    }

    /**
     * Get the file URL.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Check if the file exists in storage.
     */
    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * Delete the file from storage.
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::disk($this->disk)->delete($this->path);
        }
        
        return true;
    }

    /**
     * Get the file contents.
     */
    public function getContents(): string
    {
        if ($this->fileExists()) {
            return Storage::disk($this->disk)->get($this->path);
        }
        
        return '';
    }

    /**
     * Get the file size in bytes.
     */
    public function getSizeInBytes(): int
    {
        return $this->size;
    }

    /**
     * Get the file size in kilobytes.
     */
    public function getSizeInKb(): float
    {
        return round($this->size / 1024, 2);
    }

    /**
     * Get the file size in megabytes.
     */
    public function getSizeInMb(): float
    {
        return round($this->size / 1024 / 1024, 2);
    }

    /**
     * Get the uploader name.
     */
    public function getUploaderNameAttribute(): ?string
    {
        return $this->uploadedBy ? $this->uploadedBy->name : null;
    }

    /**
     * Get full attachment information.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'human_readable_size' => $this->human_readable_size,
            'path' => $this->path,
            'disk' => $this->disk,
            'url' => $this->url,
            'description' => $this->description,
            'extension' => $this->extension,
            'icon' => $this->icon,
            'is_image' => $this->isImage(),
            'is_document' => $this->isDocument(),
            'is_video' => $this->isVideo(),
            'is_audio' => $this->isAudio(),
            'file_exists' => $this->fileExists(),
            'uploader_id' => $this->uploaded_by,
            'uploader_name' => $this->uploader_name,
            'attachable_type' => $this->attachable_type,
            'attachable_id' => $this->attachable_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function ($attachment) {
            // Delete the file from storage when attachment is deleted
            $attachment->deleteFile();
        });
    }
}