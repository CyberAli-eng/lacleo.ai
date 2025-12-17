<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Auth\Events\Login;

class SetAuthCookieOnLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        // Create a new personal access token for the user
        $token = $user->createToken(
            'WebApp',
            ['*'],
            now()->addWeek()
        )->plainTextToken;

        $secure = !$this->isLocalDomain();
        $sameSite = $this->isLocalDomain() ? 'strict' : 'none';

        // Queue the cookie with the correct attributes.
        Cookie::queue(
            config('sanctum.access_token'), // Cookie name
            $token,                         // Cookie value
            10080,                          // Expiration time, in minutes (7 days)
            null,                           // Path
            '.' . $this->getMainDomain(),   // Domain, starting with a dot for subdomain inclusivity
            $secure,                        // Secure flag
            false,                          // HTTP only flag
            true,                           // Raw flag
            $sameSite                       // SameSite attribute
        );
    }

    /**
     * Check if the request is from the local domain.
     *
     * @return bool
     */
    private function isLocalDomain(): bool
    {
        $localDomain = 'local-accounts.lacleo.test';
        return str_contains(request()->getHost(), $localDomain);
    }

    public function getMainDomain()
    {
        $host = request()->getHost();
        $parts = explode('.', $host);

        // Remove the subdomain
        array_shift($parts);

        // Join the remaining parts back together
        return implode('.', $parts);
    }
}
