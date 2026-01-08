<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

// Custom login endpoint that returns token
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('guest');

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

// JSON current-user endpoint for SPA
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
