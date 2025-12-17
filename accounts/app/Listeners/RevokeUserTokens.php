<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cookie;

class RevokeUserTokens
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
    public function handle(Logout $event): void
    {
        // Get the user from the event object
        $user = $event->user;

        if ($user) {
            // Revoke all tokens...
            $user->tokens()->delete();
        }

        // Prepare the attributes to match those used when setting the cookie
        $secure = !$this->isLocalDomain();
        $sameSite = $this->isLocalDomain() ? 'strict' : 'none';
        $domain = '.' . $this->getMainDomain(); // Ensure this matches exactly

        // Forget the cookie with the exact attributes it was set with
        $cookie = Cookie::forget(config('sanctum.access_token'), '/', $domain);
        $cookie->withSecure($secure)->withSameSite($sameSite);

        // Queue the cookie for deletion
        Cookie::queue($cookie);

        // Attempt to forget the cookie with SameSite=strict (for users with old cookies)
        $cookieOld = Cookie::forget(config('sanctum.access_token'), '/', $domain);
        $cookieOld->withSecure($secure)->withSameSite('strict');

        // Queue the cookie for deletion
        Cookie::queue($cookieOld);
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
}
