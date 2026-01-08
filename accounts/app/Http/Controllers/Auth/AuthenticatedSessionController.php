<?php

namespace App\Http\Controllers\Auth;

use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as BaseController;
use Laravel\Fortify\Contracts\LoginResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class AuthenticatedSessionController extends BaseController
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(\Laravel\Fortify\Http\Requests\LoginRequest $request)
    {
        $request->authenticate();
        $request->session()->regenerate();

        // Create Sanctum token for API authentication
        $user = $request->user();
        $token = $user->createToken('web-app', ['*'], now()->addDays(30))->plainTextToken;

        $frontendUrl = env('WEB_APP_URL', 'https://lacleo-ai.vercel.app');

        // Redirect using Inertia location to avoid CORS/XHR issues
        return Inertia::location($frontendUrl . '?token=' . $token);
    }
}
