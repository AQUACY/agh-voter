<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureElectoralCommission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canOperateDesk()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Electoral Commission access required.'], 403);
            }

            return redirect()->route('ec.login');
        }

        return $next($request);
    }
}
