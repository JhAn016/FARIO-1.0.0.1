<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\AuthController;
use Closure;
use Illuminate\Http\Request;

class VerificarPermiso
{
    // Segmentos de URL cuyo nombre no coincide 1:1 con el módulo de permisos.
    private const MAPA_MODULO = [
        'productos'        => 'inventario',
        'config'           => 'configuracion',
        'movimientos-caja' => 'caja',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->attributes->get('_authUser');
        if (!$user)
            return response()->json(['ok' => false, 'msg' => 'No autenticado'], 401);

        // La ruta real es /api/{modulo}/... — segment(1) es siempre "api"
        $segmento = $request->segment(2);
        $modulo   = self::MAPA_MODULO[$segmento] ?? $segmento;

        $permisos = AuthController::permisosParaRol($user->rol ?? '');

        if (!in_array($modulo, $permisos, true) && !self::lecturaCompartida($request, $modulo, $permisos))
            return response()->json(['ok' => false, 'msg' => 'No tienes permiso para acceder a este módulo'], 403);

        return $next($request);
    }

    /**
     * Datos que otros módulos necesitan LEER para funcionar (p. ej. Ventas necesita
     * el catálogo de productos aunque el rol no tenga "inventario"). Solo GET:
     * crear, editar o borrar sigue exigiendo el permiso del módulo dueño.
     */
    private static function lecturaCompartida(Request $request, $modulo, array $permisos)
    {
        if (!$request->isMethod('GET'))
            return false;

        // módulo solicitado => módulos que pueden leerlo
        $lectores = [
            'inventario'  => ['ventas', 'cotizaciones', 'alquileres', 'compras'],
            'clientes'    => ['ventas', 'cotizaciones', 'alquileres'],
            'proveedores' => ['compras'],
            'ventas'      => ['devoluciones'],
            'configuracion' => '*',
        ];

        // El Dashboard es la página de inicio de todos los roles
        if ($modulo === 'reportes' && $request->segment(3) === 'dashboard')
            return true;

        if (!isset($lectores[$modulo]))
            return false;

        // El kardex de un producto es información interna de inventario
        if ($modulo === 'inventario' && $request->segment(4) === 'movimientos')
            return false;

        if ($lectores[$modulo] === '*')
            return true;

        return count(array_intersect($lectores[$modulo], $permisos)) > 0;
    }
}
