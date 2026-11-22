<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CotizacionController extends Controller
{
    public function index()
    {
        $rows = DB::table('Cotizacion')->orderByDesc('id')->limit(100)->get();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $items = $request->input('items', []);
        if (!count($items))
            return response()->json(['ok' => false, 'msg' => 'La cotización debe tener al menos un producto'], 400);

        $id = DB::table('Cotizacion')->insertGetId([
            'cliente'  => $request->input('cliente', ''),
            'telefono' => $request->input('tel', ''),
            'fecha'    => now(),
            'items'    => json_encode($request->input('items', [])),
            'total'    => $request->input('total', 0),
            'notas'    => $request->input('notas', ''),
        ]);
        return response()->json(['ok' => true, 'data' => ['id' => $id]]);
    }

    public function update(Request $request, $id)
    {
        $items = $request->input('items', []);
        if (!count($items))
            return response()->json(['ok' => false, 'msg' => 'La cotización debe tener al menos un producto'], 400);

        if (!DB::table('Cotizacion')->where('id', $id)->exists())
            return response()->json(['ok' => false, 'msg' => 'Cotización no encontrada'], 404);

        // La fecha original se conserva: sigue siendo la misma cotización
        DB::table('Cotizacion')->where('id', $id)->update([
            'cliente'  => $request->input('cliente', ''),
            'telefono' => $request->input('tel', ''),
            'items'    => json_encode($items),
            'total'    => $request->input('total', 0),
            'notas'    => $request->input('notas', ''),
        ]);
        return response()->json(['ok' => true, 'msg' => 'Cotización actualizada']);
    }

    public function destroy($id)
    {
        DB::table('Cotizacion')->where('id', $id)->delete();
        return response()->json(['ok' => true, 'msg' => 'Eliminada']);
    }

    public function convertir(Request $request, $id)
    {
        $cot = DB::table('Cotizacion')->where('id', $id)->first();
        if (!$cot)
            return response()->json(['ok' => false, 'msg' => 'Cotización no encontrada'], 404);

        $items = json_decode($cot->items, true) ?: [];
        if (!count($items))
            return response()->json(['ok' => false, 'msg' => 'La cotización no tiene productos'], 400);

        // Validar stock antes de la transacción
        foreach ($items as $it) {
            $prod = DB::table('Herramienta')->where('id_herramienta', $it['id'])->first();
            $cant = (int)($it['cant'] ?? 1);
            if (!$prod || $prod->stock < $cant) {
                $disp = $prod ? $prod->stock : 0;
                $nom  = $prod ? $prod->nombre : "ID {$it['id']}";
                return response()->json([
                    'ok'  => false,
                    'msg' => "Stock insuficiente para «{$nom}» (disponible: {$disp}, solicitado: {$cant})"
                ], 400);
            }
        }

        $result = DB::transaction(function() use ($cot, $items, $request) {
            // Vincular al cliente real de la cotización (por nombre) en vez de
            // asignar siempre "Consumidor Final", para no perder la trazabilidad.
            $cliId = 1;
            $nombreCliente = trim($cot->cliente ?? '');
            if ($nombreCliente) {
                $existente = DB::table('Cliente')->where('nombre', $nombreCliente)->first();
                $cliId = $existente
                    ? $existente->id_cliente
                    : DB::table('Cliente')->insertGetId([
                        'nombre'   => $nombreCliente,
                        'telefono' => $cot->telefono ?: '',
                        'estado'   => 'activo',
                    ]);
            }

            $anio = now()->year;
            $nro  = DB::table('Venta')->whereYear('fecha', $anio)->lockForUpdate()->count() + 1;
            $numeroFactura = 'FAC-' . $anio . '-' . str_pad($nro, 4, '0', STR_PAD_LEFT);

            $vid = DB::table('Venta')->insertGetId([
                'fecha'          => now(),
                'total'          => $cot->total,
                'numero_factura' => $numeroFactura,
                'id_cliente'     => $cliId,
                'id_usuario'     => $request->input('usuarioId', 1),
                'metodo_pago'    => $request->input('pago', 'Efectivo'),
            ]);

            foreach ($items as $it) {
                DB::table('Detalle_Venta')->insert([
                    'id_venta'       => $vid,
                    'id_herramienta' => $it['id'],
                    'cantidad'       => $it['cant'],
                    'precio'         => $it['precio'],
                ]);
                DB::table('Herramienta')->where('id_herramienta', $it['id'])->decrement('stock', $it['cant']);
            }

            DB::table('Cotizacion')->where('id', $cot->id)->delete();

            return ['id_venta' => $vid, 'numero_factura' => $numeroFactura];
        });

        return response()->json(['ok' => true, 'data' => $result, 'msg' => 'Cotización convertida en venta ' . $result['numero_factura']]);
    }
}
