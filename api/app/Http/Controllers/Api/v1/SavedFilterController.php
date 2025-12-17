<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\SavedFilter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SavedFilterController extends Controller
{
    /**
     * List all saved filters for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = SavedFilter::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('entity_type', $request->query('type'));
        }

        return response()->json([
            'data' => $query->get()
        ]);
    }

    /**
     * Store a new saved filter
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'filters' => 'required|array',
            'entity_type' => 'required|in:contact,company',
            'tags' => 'nullable|array',
            'tags.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $validated['user_id'] = $user->id;

        try {
            $savedFilter = SavedFilter::create($validated);
            return response()->json(['data' => $savedFilter], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create saved filter', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to save filter'], 500);
        }
    }

    /**
     * Delete a saved filter
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $savedFilter = SavedFilter::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$savedFilter) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $savedFilter->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    /**
     * Update/Rename logic if needed
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $savedFilter = SavedFilter::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$savedFilter) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_starred' => 'sometimes|boolean',
            'filters' => 'sometimes|array', // Allow updating content too
            'tags' => 'sometimes|array',
        ]);

        $savedFilter->update($validated);

        return response()->json(['data' => $savedFilter]);
    }
}
