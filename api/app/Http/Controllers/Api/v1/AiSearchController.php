<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\AiQueryTranslatorService;
use Illuminate\Http\Request;

class AiSearchController extends Controller
{
    public function __construct(
        protected AiQueryTranslatorService $translator
    ) {
    }

    /**
     * Translate natural language query to filters.
     */
    public function translate(Request $request)
    {
        $validated = $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|in:user,assistant',
            'messages.*.content' => 'required|string|max:2000',
            'context' => 'nullable|array',
            'context.lastResultCount' => 'nullable|integer',
        ]);

        $result = $this->translator->translate(
            $validated['messages'],
            $validated['context'] ?? []
        );

        return response()->json($result);
    }
}
