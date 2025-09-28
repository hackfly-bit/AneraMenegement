<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentController extends Controller
{
    /**
     * Get content list.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $contentPath = storage_path('app/content');
            $files = [];

            if (file_exists($contentPath)) {
                $iterator = new \DirectoryIterator($contentPath);
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'json') {
                        $content = json_decode(file_get_contents($file->getPathname()), true);
                        $files[] = [
                            'id' => str_replace('.json', '', $file->getFilename()),
                            'title' => $content['title'] ?? 'Untitled',
                            'type' => $content['type'] ?? 'page',
                            'status' => $content['status'] ?? 'draft',
                            'updated_at' => date('Y-m-d H:i:s', $file->getMTime()),
                            'created_at' => date('Y-m-d H:i:s', $file->getCTime()),
                        ];
                    }
                }
            }

            return response()->json([
                'data' => $files,
                'message' => 'Content list retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve content list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new content.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:page,post,block',
            'status' => 'required|in:draft,published,archived',
            'metadata' => 'nullable|array',
            'slug' => 'nullable|string|max:255',
        ]);

        try {
            $id = $validated['slug'] ?? Str::slug($validated['title']);
            $filename = $id . '.json';
            $contentPath = storage_path('app/content');

            if (!file_exists($contentPath)) {
                mkdir($contentPath, 0755, true);
            }

            $content = [
                'id' => $id,
                'title' => $validated['title'],
                'content' => $validated['content'],
                'type' => $validated['type'],
                'status' => $validated['status'],
                'metadata' => $validated['metadata'] ?? [],
                'slug' => $id,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            file_put_contents($contentPath . '/' . $filename, json_encode($content, JSON_PRETTY_PRINT));

            return response()->json([
                'data' => $content,
                'message' => 'Content created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific content.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $filename = $id . '.json';
            $filePath = storage_path('app/content/' . $filename);

            if (!file_exists($filePath)) {
                return response()->json([
                    'message' => 'Content not found'
                ], 404);
            }

            $content = json_decode(file_get_contents($filePath), true);

            return response()->json([
                'data' => $content,
                'message' => 'Content retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update content.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'type' => 'sometimes|in:page,post,block',
            'status' => 'sometimes|in:draft,published,archived',
            'metadata' => 'nullable|array',
        ]);

        try {
            $filename = $id . '.json';
            $filePath = storage_path('app/content/' . $filename);

            if (!file_exists($filePath)) {
                return response()->json([
                    'message' => 'Content not found'
                ], 404);
            }

            $content = json_decode(file_get_contents($filePath), true);
            $content = array_merge($content, $validated);
            $content['updated_at'] = now()->toDateTimeString();

            file_put_contents($filePath, json_encode($content, JSON_PRETTY_PRINT));

            return response()->json([
                'data' => $content,
                'message' => 'Content updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete content.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $filename = $id . '.json';
            $filePath = storage_path('app/content/' . $filename);

            if (!file_exists($filePath)) {
                return response()->json([
                    'message' => 'Content not found'
                ], 404);
            }

            unlink($filePath);

            return response()->json([
                'message' => 'Content deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload media file.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'type' => 'nullable|in:image,document,video,audio',
        ]);

        try {
            $file = $request->file('file');
            $type = $validated['type'] ?? 'image';
            
            $path = $file->store('public/media');
            $url = Storage::url($path);

            return response()->json([
                'data' => [
                    'path' => $path,
                    'url' => $url,
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'type' => $type,
                ],
                'message' => 'Media uploaded successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload media: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get media list.
     *
     * @return JsonResponse
     */
    public function getMedia(): JsonResponse
    {
        try {
            $files = Storage::files('public/media');
            $media = [];

            foreach ($files as $file) {
                $media[] = [
                    'path' => $file,
                    'url' => Storage::url($file),
                    'filename' => basename($file),
                    'size' => Storage::size($file),
                    'type' => $this->getFileType($file),
                    'created_at' => date('Y-m-d H:i:s', Storage::lastModified($file)),
                ];
            }

            return response()->json([
                'data' => $media,
                'message' => 'Media list retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve media list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete media file.
     *
     * @param string $path
     * @return JsonResponse
     */
    public function deleteMedia(string $path): JsonResponse
    {
        try {
            if (!Storage::exists($path)) {
                return response()->json([
                    'message' => 'Media file not found'
                ], 404);
            }

            Storage::delete($path);

            return response()->json([
                'message' => 'Media file deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete media file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file type based on extension.
     *
     * @param string $filename
     * @return string
     */
    private function getFileType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'];
        $audioExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'flac'];
        $documentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

        if (in_array($extension, $imageExtensions)) return 'image';
        if (in_array($extension, $videoExtensions)) return 'video';
        if (in_array($extension, $audioExtensions)) return 'audio';
        if (in_array($extension, $documentExtensions)) return 'document';
        
        return 'other';
    }
}