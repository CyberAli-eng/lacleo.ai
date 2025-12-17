<?php

namespace App\Http\Middleware;

use App\Models\Contact;
use App\Services\RecordNormalizer;
use Closure;
use Illuminate\Http\Request;

class EnsureRevealFieldAvailable
{
    public function handle(Request $request, Closure $next, string $field)
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $id = (string) ($request->input('contact_id') ?? $request->input('id') ?? $request->input('_id'));
        if ($id === '') {
            return response()->json(['error' => 'Contact id required'], 422);
        }
        $doc = Contact::findInElastic($id);
        if (! $doc) {
            return response()->json(['error' => 'Contact not found'], 404);
        }
        if (! in_array($field, ['email', 'phone'], true)) {
            return response()->json(['error' => 'Invalid field'], 400);
        }

        // Accept either Elastic model objects or arrays
        $docArr = is_object($doc) && method_exists($doc, 'toArray') ? $doc->toArray() : (is_array($doc) ? $doc : (array) $doc);

        $exists = $field === 'email'
            ? RecordNormalizer::hasEmail($docArr)
            : RecordNormalizer::hasPhone($docArr);

        if (! $exists) {
            return response()->json(['error' => ucfirst($field).' not available for this contact'], 422);
        }

        return $next($request);
    }
}
