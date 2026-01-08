<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

// Custom login endpoint that returns token
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('guest');

// Helper to get frontend token URL
function getAppRedirectUrl($user)
{
    if (!$user)
        return route('login');
    $token = $user->createToken('web-app', ['*'], now()->addDays(30))->plainTextToken;
    $frontendUrl = env('WEB_APP_URL', 'https://lacleo-ai.vercel.app');
    return $frontendUrl . '?token=' . $token;
}

// Redirect root to SSO flow
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->away(getAppRedirectUrl(Auth::user()));
    }
    return redirect()->route('login');
});

// SSO Verification Route (Protected by Session)
Route::middleware(['auth:web'])->get('/sso/verify', function () {
    return redirect()->away(getAppRedirectUrl(Auth::user()));
});

// Dashboard should mostly redirect to app now
Route::middleware(['auth:web'])->get('/dashboard', function () {
    return redirect()->away(getAppRedirectUrl(Auth::user()));
})->name('dashboard');

// JSON current-user endpoint for SPA (Token Only)
Route::middleware(['auth:sanctum'])->get('/user', function () {
    $user = Auth::user();
    if (!$user) {
        return response()->json(['error' => 'UNAUTHENTICATED'], 401);
    }
    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'email_verified' => method_exists($user, 'hasVerifiedEmail') ? (bool) $user->hasVerifiedEmail() : (bool) ($user->email_verified ?? false),
        'profile_photo_url' => property_exists($user, 'profile_photo_url') ? $user->profile_photo_url : null,
    ]);
});
