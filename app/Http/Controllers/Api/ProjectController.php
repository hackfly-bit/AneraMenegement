<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    protected ProjectService $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    /**
     * Display a listing of the projects.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'client_id' => $request->get('client_id'),
            'priority' => $request->get('priority'),
            'start_date_from' => $request->get('start_date_from'),
            'start_date_to' => $request->get('start_date_to'),
            'end_date_from' => $request->get('end_date_from'),
            'end_date_to' => $request->get('end_date_to'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
            'per_page' => $request->get('per_page', 15),
        ];

        $projects = $this->projectService->getAllProjects($filters);

        return response()->json([
            'data' => $projects->items(),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created project in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:planning,in_progress,on_hold,completed,cancelled',
            'progress' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $project = $this->projectService->createProject($validated);

        return response()->json([
            'data' => $project->load(['client', 'products']),
            'message' => 'Project created successfully'
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified project.
     *
     * @param Project $project
     * @return JsonResponse
     */
    public function show(Project $project): JsonResponse
    {
        $project->load(['client', 'products', 'invoices', 'tasks']);

        return response()->json([
            'data' => $project
        ]);
    }

    /**
     * Update the specified project in storage.
     *
     * @param Request $request
     * @param Project $project
     * @return JsonResponse
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'sometimes|required|exists:clients,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:planning,in_progress,on_hold,completed,cancelled',
            'progress' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $updatedProject = $this->projectService->updateProject($project, $validated);

        return response()->json([
            'data' => $updatedProject->load(['client', 'products']),
            'message' => 'Project updated successfully'
        ]);
    }

    /**
     * Remove the specified project from storage.
     *
     * @param Project $project
     * @return JsonResponse
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->projectService->deleteProject($project);

        return response()->json([
            'message' => 'Project deleted successfully'
        ], Response::HTTP_NO_CONTENT);
    }

    /**
     * Update project status.
     *
     * @param Request $request
     * @param Project $project
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:planning,in_progress,on_hold,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $updatedProject = $this->projectService->updateProjectStatus($project, $validated['status'], $validated['notes'] ?? null);

        return response()->json([
            'data' => $updatedProject,
            'message' => 'Project status updated successfully'
        ]);
    }

    /**
     * Update project progress.
     *
     * @param Request $request
     * @param Project $project
     * @return JsonResponse
     */
    public function updateProgress(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'progress' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $updatedProject = $this->projectService->updateProjectProgress($project, $validated['progress'], $validated['notes'] ?? null);

        return response()->json([
            'data' => $updatedProject,
            'message' => 'Project progress updated successfully'
        ]);
    }

    /**
     * Get project statistics.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $stats = $this->projectService->getProjectStats();

        return response()->json([
            'data' => $stats
        ]);
    }

    /**
     * Get projects by client.
     *
     * @param Request $request
     * @param int $clientId
     * @return JsonResponse
     */
    public function byClient(Request $request, int $clientId): JsonResponse
    {
        $filters = [
            'status' => $request->get('status'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
            'per_page' => $request->get('per_page', 15),
        ];

        $projects = $this->projectService->getProjectsByClient($clientId, $filters);

        return response()->json([
            'data' => $projects->items(),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
            ]
        ]);
    }
}