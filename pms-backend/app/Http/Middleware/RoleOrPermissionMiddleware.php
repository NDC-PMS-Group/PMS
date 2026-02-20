<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleOrPermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $roleOrPermission): Response
    {
        if (! $request->user()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        if (! $request->user()->hasRole($roleOrPermission) && ! $request->user()->hasPermissionTo($roleOrPermission)) {
            return response()->json([
                'message' => 'Unauthorized. Required role or permission: ' . $roleOrPermission
            ], 403);
        }

        return $next($request);
    }
}