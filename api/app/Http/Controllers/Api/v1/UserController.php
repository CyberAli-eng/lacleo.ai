<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\v1\UserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getUser(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return new UserResource($user);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $items = \App\Models\User::query()
            ->where(function ($builder) use ($q) {
                $builder->where('email', 'like', '%'.$q.'%')
                    ->orWhere('name', 'like', '%'.$q.'%');
            })
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json($items);
    }
}
