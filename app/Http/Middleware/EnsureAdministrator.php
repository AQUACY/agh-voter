<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Administrator access required.'], 403);
            }

            return redirect()->route('ec.dashboard')->withErrors([
                'election' => 'Only an administrator can restore the voting period.',
            ]);
        }

        return $next($request);
    }
}
