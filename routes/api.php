<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\VentaController;
use App\Http\Controllers\Api\AlquilerController;
use App\Http\Controllers\Api\CotizacionController;
use App\Http\Controllers\Api\CajaController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\CompraController;
use App\Http\Controllers\Api\DevolucionController;

// ── Rutas públicas (sin sesión) ──────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

Route::get('/ping', function() {
    return response()->json(['ok' => true, 'data' => ['status' => 'online']]);
});

// ── Requiere estar logueado, pero sin restricción de módulo ──
Route::middleware('auth.token')->group(function () {
    Route::post('/cambiar-password', [AuthController::class, 'cambiarPassword']);
});

// ── Resto de la API: requiere sesión válida + permiso del módulo ──
Route::middleware(['auth.token', 'auth.permiso'])->group(function () {

    // Rutas específicas ANTES de apiResource
    Route::get('productos/stock-critico',       [ProductoController::class, 'stockCritico']);
    Route::patch('productos/{id}/stock',        [ProductoController::class, 'ajustarStock']);
    Route::get('productos/{id}/movimientos',    [ProductoController::class, 'movimientos']);

    Route::patch('ventas/{id}/anular',          [VentaController::class, 'anular']);

    Route::patch('alquileres/{id}/devolver',    [AlquilerController::class, 'devolver']);
    Route::patch('alquileres/{id}/prorroga',    [AlquilerController::class, 'prorroga']);
    Route::patch('alquileres/{id}/estado',      [AlquilerController::class, 'cambiarEstado']);
    Route::get('alquileres/mapa',               [AlquilerController::class, 'mapa']);

    Route::get('caja/descuadre',                [CajaController::class, 'descuadre']);

    Route::post('cotizaciones/{id}/convertir',  [CotizacionController::class, 'convertir']);

    Route::get('clientes/mapa',                 [ClienteController::class, 'mapa']);
    Route::get('clientes/{id}/historial',       [ClienteController::class, 'historial']);

    // Recursos CRUD
    Route::apiResource('usuarios',     \App\Http\Controllers\Api\UsuarioController::class);
    Route::apiResource('clientes',     ClienteController::class);
    Route::apiResource('proveedores',  ProveedorController::class);
    Route::apiResource('productos',    ProductoController::class);
    Route::apiResource('ventas',       VentaController::class);
    Route::apiResource('alquileres',   AlquilerController::class);
    Route::apiResource('cotizaciones', CotizacionController::class);

    // Caja
    Route::get('caja',              [CajaController::class, 'index']);
    Route::post('caja',             [CajaController::class, 'operar']);
    Route::get('movimientos-caja',  [CajaController::class, 'movimientos']);
    Route::post('movimientos-caja', [CajaController::class, 'crearMovimiento']);

    // Reportes
    Route::get('reportes/dashboard',      [ReporteController::class, 'dashboard']);
    Route::get('reportes/ventas',         [ReporteController::class, 'ventas']);
    Route::get('reportes/graficos',       [ReporteController::class, 'graficos']);
    Route::get('reportes/ventas-por-mes', [ReporteController::class, 'ventasPorMes']);
    Route::get('reportes/analisis',       [ReporteController::class, 'analisis']);

    // Configuración
    Route::get('config',  [ConfigController::class, 'index']);
    Route::post('config', [ConfigController::class, 'guardar']);

    // Compras
    Route::get('compras',  [CompraController::class, 'index']);
    Route::post('compras', [CompraController::class, 'store']);

    // Devoluciones
    Route::get('devoluciones',  [DevolucionController::class, 'index']);
    Route::post('devoluciones', [DevolucionController::class, 'store']);
});
