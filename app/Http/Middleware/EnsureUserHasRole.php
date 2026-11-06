<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['message' => 'No autenticado.'], 401)
                : redirect()->route('login');
        }

        if (! in_array($user->role->value, $roles, true)) {
            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['message' => 'No autorizado para esta acción.'], 403)
                : abort(403, 'No autorizado para esta acción.');
        }

        return $next($request);
    }
}
