<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVoterAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('voter_id')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Verify your Staff ID first.'], 401);
            }

            return redirect()->route('voter.enter');
        }

        return $next($request);
    }
}
