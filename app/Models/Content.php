<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Content extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'type',
        'status',
        'published_at',
        'meta_title',
        'meta_description',
        'author_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'published_at' => 'datetime',
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
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:contents,slug',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string|max:1000',
            'type' => 'required|in:page,post,document',
            'status' => 'required|in:draft,published,archived',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'author_id' => 'nullable|exists:users,id',
        ];
    }

    /**
     * Get the author that owns the content.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Scope a query to only include content by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include content by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include published content.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where('published_at', '<=', now());
    }

    /**
     * Scope a query to only include draft content.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include archived content.
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Scope a query to search content by title or content.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', '%' . $search . '%')
              ->orWhere('content', 'like', '%' . $search . '%')
              ->orWhere('excerpt', 'like', '%' . $search . '%');
        });
    }

    /**
     * Check if the content is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && 
               $this->published_at && 
               $this->published_at <= now();
    }

    /**
     * Check if the content is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the content is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Check if the content is a page.
     */
    public function isPage(): bool
    {
        return $this->type === 'page';
    }

    /**
     * Check if the content is a post.
     */
    public function isPost(): bool
    {
        return $this->type === 'post';
    }

    /**
     * Check if the content is a document.
     */
    public function isDocument(): bool
    {
        return $this->type === 'document';
    }

    /**
     * Get the content URL.
     */
    public function getUrlAttribute(): string
    {
        return '/' . $this->slug;
    }

    /**
     * Get the content status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the content type display name.
     */
    public function getTypeDisplayAttribute(): string
    {
        return match ($this->type) {
            'page' => 'Page',
            'post' => 'Post',
            'document' => 'Document',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get the formatted published date.
     */
    public function getFormattedPublishedDateAttribute(): ?string
    {
        return $this->published_at ? $this->published_at->format('F j, Y') : null;
    }

    /**
     * Get the reading time in minutes.
     */
    public function getReadingTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));
        return max(1, ceil($wordCount / 200)); // Assuming 200 words per minute
    }

    /**
     * Get the excerpt if not set.
     */
    public function getExcerptAttribute($value): string
    {
        if ($value) {
            return $value;
        }
        
        // Generate excerpt from content if not provided
        $content = strip_tags($this->attributes['content'] ?? '');
        $excerpt = Str::limit($content, 200);
        
        return $excerpt;
    }

    /**
     * Get SEO-friendly meta title.
     */
    public function getSeoTitleAttribute(): string
    {
        return $this->meta_title ?: $this->title;
    }

    /**
     * Get SEO-friendly meta description.
     */
    public function getSeoDescriptionAttribute(): string
    {
        return $this->meta_description ?: $this->excerpt;
    }

    /**
     * Publish the content.
     */
    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * Unpublish the content.
     */
    public function unpublish(): void
    {
        $this->update([
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Archive the content.
     */
    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    /**
     * Generate a unique slug.
     */
    public function generateSlug(): string
    {
        $slug = Str::slug($this->title);
        $originalSlug = $slug;
        $counter = 1;
        
        // Ensure uniqueness
        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Get related content by type.
     */
    public function getRelatedContent(int $limit = 5)
    {
        return static::published()
                    ->where('type', $this->type)
                    ->where('id', '!=', $this->id)
                    ->limit($limit)
                    ->get();
    }

    /**
     * Get full content information.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'url' => $this->url,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'type' => $this->type,
            'type_display' => $this->type_display,
            'status' => $this->status,
            'status_display' => $this->status_display,
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'formatted_published_date' => $this->formatted_published_date,
            'reading_time' => $this->reading_time,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'author_id' => $this->author_id,
            'author' => $this->author,
            'is_published' => $this->isPublished(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($content) {
            // Generate slug if not provided
            if (empty($content->slug)) {
                $content->slug = $content->generateSlug();
            }
            
            // Set default status if not provided
            if (empty($content->status)) {
                $content->status = 'draft';
            }
            
            // Set default type if not provided
            if (empty($content->type)) {
                $content->type = 'page';
            }
        });

        static::updating(function ($content) {
            // Regenerate slug if title changed and slug is not manually set
            if ($content->isDirty('title') && !$content->isDirty('slug')) {
                $content->slug = $content->generateSlug();
            }
            
            // Update published_at when publishing
            if ($content->isDirty('status') && $content->status === 'published' && !$content->published_at) {
                $content->published_at = now();
            }
            
            // Clear published_at when unpublishing
            if ($content->isDirty('status') && $content->status !== 'published') {
                $content->published_at = null;
            }
        });
    }
}