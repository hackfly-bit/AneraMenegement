<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    protected ClientService $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Display a listing of the clients.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
            'per_page' => $request->get('per_page', 15),
        ];

        $clients = $this->clientService->getAllClients($filters);

        return response()->json([
            'data' => $clients->items(),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
                'from' => $clients->firstItem(),
                'to' => $clients->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created client in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $client = $this->clientService->createClient($validated);

        return response()->json([
            'data' => $client,
            'message' => 'Client created successfully'
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified client.
     *
     * @param Client $client
     * @return JsonResponse
     */
    public function show(Client $client): JsonResponse
    {
        $client->load(['projects', 'invoices']);

        return response()->json([
            'data' => $client
        ]);
    }

    /**
     * Update the specified client in storage.
     *
     * @param Request $request
     * @param Client $client
     * @return JsonResponse
     */
    public function update(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:clients,email,' . $client->id,
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $updatedClient = $this->clientService->updateClient($client, $validated);

        return response()->json([
            'data' => $updatedClient,
            'message' => 'Client updated successfully'
        ]);
    }

    /**
     * Remove the specified client from storage.
     *
     * @param Client $client
     * @return JsonResponse
     */
    public function destroy(Client $client): JsonResponse
    {
        $this->clientService->deleteClient($client);

        return response()->json([
            'message' => 'Client deleted successfully'
        ], Response::HTTP_NO_CONTENT);
    }

    /**
     * Get client statistics.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $stats = $this->clientService->getClientStats();

        return response()->json([
            'data' => $stats
        ]);
    }

    /**
     * Search clients.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = $request->get('limit', 10);

        $clients = $this->clientService->searchClients($query, $limit);

        return response()->json([
            'data' => $clients
        ]);
    }
}