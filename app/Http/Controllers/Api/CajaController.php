<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    public function index()
    {
        return response()->json(['ok' => true, 'data' => DB::table('Caja')->orderByDesc('id_caja')->limit(30)->get()]);
    }

    public function operar(Request $request)
    {
        $tipo  = $request->input('tipo', 'apertura');
        $monto = (float)$request->input('monto', 0);
        $uid   = $request->input('usuarioId', 1);

        if ($tipo === 'apertura') {
            $existente = DB::table('Caja')->whereDate('fecha', now()->toDateString())->where('estado', 'abierta')->first();
            if ($existente)
                return response()->json(['ok' => false, 'msg' => 'Ya hay una caja abierta hoy'], 400);

            $id = DB::table('Caja')->insertGetId([
                'fecha'         => now()->toDateString(),
                'saldo_inicio'  => $monto,
                'estado'        => 'abierta',
                'observaciones' => $request->input('obs', 'Apertura de caja'),
                'id_usuario'    => $uid,
            ]);
            return response()->json(['ok' => true, 'data' => ['id' => $id]]);
        }

        $caja = DB::table('Caja')->whereDate('fecha', now()->toDateString())->where('estado', 'abierta')->orderByDesc('id_caja')->first();
        if (!$caja)
            return response()->json(['ok' => false, 'msg' => 'No hay caja abierta hoy'], 404);

        DB::table('Caja')->where('id_caja', $caja->id_caja)->update([
            'saldo_final'   => $monto,
            'estado'        => 'cerrada',
            'observaciones' => $request->input('obs', 'Cierre de caja'),
        ]);
        return response()->json(['ok' => true, 'data' => ['id' => $caja->id_caja]]);
    }

    public function movimientos()
    {
        return response()->json(['ok' => true, 'data' => DB::table('Movimiento_Caja')->orderByDesc('id_movimiento')->limit(200)->get()]);
    }

    public function crearMovimiento(Request $request)
    {
        $caja = DB::table('Caja')->whereDate('fecha', now()->toDateString())->where('estado', 'abierta')->orderByDesc('id_caja')->first();
        if (!$caja)
            return response()->json(['ok' => false, 'msg' => 'Debes abrir la caja del día antes de registrar movimientos'], 400);

        $monto = (float)$request->input('monto', 0);
        if ($monto <= 0)
            return response()->json(['ok' => false, 'msg' => 'El monto debe ser mayor a cero'], 400);

        $id = DB::table('Movimiento_Caja')->insertGetId([
            'tipo'        => $request->input('tipo', 'ingreso'),
            'concepto'    => $request->input('concepto', ''),
            'monto'       => $monto,
            'metodo_pago' => $request->input('metodoPago', 'Efectivo'),
            'fecha'       => now(),
            'id_caja'     => $caja->id_caja,
        ]);
        return response()->json(['ok' => true, 'data' => ['id' => $id]]);
    }

    public function descuadre()
    {
        $caja = DB::table('Caja')->whereDate('fecha', now()->toDateString())->orderByDesc('id_caja')->first();
        if (!$caja)
            return response()->json(['ok' => true, 'data' => ['saldoTeorico'=>0,'ingresosEfectivo'=>0,'transferencias'=>0,'egresos'=>0,'montoApertura'=>0,'estado'=>'']]);

        $cid      = $caja->id_caja;
        $apertura = (float)($caja->saldo_inicio ?? 0);
        $ef  = (float)DB::table('Movimiento_Caja')->where('id_caja',$cid)->where('tipo','ingreso')->where('metodo_pago','Efectivo')->sum('monto');
        $tr  = (float)DB::table('Movimiento_Caja')->where('id_caja',$cid)->where('tipo','ingreso')->where('metodo_pago','!=','Efectivo')->sum('monto');
        $eg  = (float)DB::table('Movimiento_Caja')->where('id_caja',$cid)->where('tipo','egreso')->sum('monto');

        return response()->json(['ok' => true, 'data' => [
            'montoApertura'    => $apertura,
            'ingresosEfectivo' => $ef,
            'transferencias'   => $tr,
            'egresos'          => $eg,
            'saldoTeorico'     => $apertura + $ef - $eg,
            'estado'           => $caja->estado,
        ]]);
    }
}