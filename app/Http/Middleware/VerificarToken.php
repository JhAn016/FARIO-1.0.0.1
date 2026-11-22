<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerificarToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token)
            return response()->json(['ok' => false, 'msg' => 'No autenticado'], 401);

        $user = DB::table('Usuario')
            ->where('api_token', $token)
            ->where('estado', 'activo')
            ->first();

        if (!$user)
            return response()->json(['ok' => false, 'msg' => 'Sesión inválida o expirada'], 401);

        $request->attributes->set('_authUser', $user);
        return $next($request);
    }
}
