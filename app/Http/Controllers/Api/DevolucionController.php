<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevolucionController extends Controller
{
    public function index()
    {
        $rows = DB::table('Devolucion as d')
            ->leftJoin('Venta as v','d.id_venta','=','v.id_venta')
            ->select('d.*')
            ->orderByDesc('d.id')->get();
        return response()->json(['ok'=>true,'data'=>$rows]);
    }

    public function store(Request $request)
    {
        $ventaId = $request->input('ventaId');
        $items   = $request->input('items', []);
        $total   = (float)$request->input('total', 0);

        if (!$ventaId)
            return response()->json(['ok'=>false,'msg'=>'Debes indicar la venta a la que pertenece la devolución'],400);
        if (!count($items))
            return response()->json(['ok'=>false,'msg'=>'La devolución debe tener al menos un producto'],400);

        $venta = DB::table('Venta')->where('id_venta', $ventaId)->first();
        if (!$venta)
            return response()->json(['ok'=>false,'msg'=>'La venta indicada no existe'],404);

        // Validar que cada ítem pertenezca a esa venta y que no se devuelva más de lo vendido
        foreach ($items as $it) {
            $hid  = $it['id'] ?? 0;
            $cant = (int)($it['cantidad'] ?? 0);
            if (!$hid || $cant <= 0)
                return response()->json(['ok'=>false,'msg'=>'Cada ítem debe tener producto y cantidad válidos'],400);

            $detalle = DB::table('Detalle_Venta')->where('id_venta', $ventaId)->where('id_herramienta', $hid)->first();
            if (!$detalle)
                return response()->json(['ok'=>false,'msg'=>"El producto ID {$hid} no pertenece a la venta #{$ventaId}"],400);

            $yaDevuelto = DB::table('Devolucion')->where('id_venta', $ventaId)->get()
                ->sum(function($d) use ($hid) {
                    $prev = json_decode($d->items, true) ?: [];
                    foreach ($prev as $p) if (($p['id'] ?? null) == $hid) return (int)($p['cantidad'] ?? 0);
                    return 0;
                });

            if ($cant + $yaDevuelto > $detalle->cantidad)
                return response()->json(['ok'=>false,'msg'=>"No puedes devolver más unidades de las vendidas para el producto ID {$hid}"],400);
        }

        $id = DB::transaction(function() use ($request, $items, $total, $ventaId) {
            foreach ($items as $it) {
                DB::table('Herramienta')->where('id_herramienta', $it['id'])->increment('stock', (int)$it['cantidad']);
            }

            $id = DB::table('Devolucion')->insertGetId([
                'id_venta' => $ventaId,
                'fecha'    => now(),
                'motivo'   => $request->input('motivo', ''),
                'usuario'  => $request->input('usuario', ''),
                'total'    => $total,
                'items'    => json_encode($items),
            ]);

            // Reflejar la salida de efectivo en la caja del día, si hay una abierta
            $caja = DB::table('Caja')
                ->whereDate('fecha', now()->toDateString())
                ->where('estado', 'abierta')
                ->orderByDesc('id_caja')->first();

            if ($caja && $total > 0) {
                DB::table('Movimiento_Caja')->insert([
                    'tipo'        => 'egreso',
                    'concepto'    => 'Devolución venta #' . $ventaId,
                    'monto'       => $total,
                    'metodo_pago' => 'Efectivo',
                    'fecha'       => now(),
                    'id_caja'     => $caja->id_caja,
                ]);
            }

            return $id;
        });

        return response()->json(['ok'=>true,'data'=>['id'=>$id]]);
    }
}
