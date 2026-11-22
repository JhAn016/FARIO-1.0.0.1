<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    public function index()
    {
        $ventas = DB::table('Venta as v')
            ->leftJoin('Cliente as c', 'v.id_cliente', '=', 'c.id_cliente')
            ->select('v.*', 'c.nombre as cliente_nombre')
            ->orderByDesc('v.id_venta')->limit(200)->get();

        $result = $ventas->map(function($v) {
            $detalles = DB::table('Detalle_Venta as dv')
                ->leftJoin('Herramienta as h', 'dv.id_herramienta', '=', 'h.id_herramienta')
                ->select('dv.*', 'h.nombre as prod_nombre')
                ->where('dv.id_venta', $v->id_venta)->get();
            return [
                'id_venta'       => $v->id_venta,
                'numero_factura' => $v->numero_factura ?? null,
                'fecha'          => $v->fecha,
                'cliNom'         => $v->cliente_nombre ?? 'Consumidor Final',
                'cliId'          => $v->id_cliente,
                'pago'           => $v->metodo_pago,
                'total'          => (float)$v->total,
                'estado'         => $v->estado ?? 'activa',
                'items'          => $detalles->map(fn($d) => [
                    'id'     => $d->id_herramienta,
                    'nombre' => $d->prod_nombre,
                    'precio' => (float)$d->precio,
                    'cant'   => (int)$d->cantidad,
                    'sub'    => (float)$d->precio * (int)$d->cantidad,
                ]),
            ];
        });
        return response()->json(['ok' => true, 'data' => $result]);
    }

    public function store(Request $request)
    {
        $items = $request->input('items', []);
        if (!count($items))
            return response()->json(['ok' => false, 'msg' => 'La venta debe tener al menos un producto'], 400);

        foreach ($items as $it) {
            if (empty($it['id']) || (int)($it['cant'] ?? 0) <= 0 || !is_numeric($it['precio'] ?? null))
                return response()->json(['ok' => false, 'msg' => 'Hay un producto en el carrito con datos inválidos'], 400);
        }

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

        $result = DB::transaction(function() use ($request, $items) {
            $anio = now()->year;
            $nro  = DB::table('Venta')->whereYear('fecha', $anio)->lockForUpdate()->count() + 1;
            $numeroFactura = 'FAC-' . $anio . '-' . str_pad($nro, 4, '0', STR_PAD_LEFT);
            $total = array_sum(array_map(fn($i) => ($i['precio'] ?? 0) * ($i['cant'] ?? 1), $items));

            $vid = DB::table('Venta')->insertGetId([
                'fecha'          => now(),
                'total'          => $total,
                'numero_factura' => $numeroFactura,
                'id_cliente'     => $request->input('cliId', 1),
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

            return ['id' => $vid, 'total' => $total, 'numero_factura' => $numeroFactura];
        });

        // Detectar alertas de stock bajo tras la venta
        $alertas = [];
        foreach ($items as $it) {
            $prod = DB::table('Herramienta')->where('id_herramienta', $it['id'])->first();
            if ($prod && $prod->stock <= $prod->stock_minimo) {
                $alertas[] = [
                    'nombre' => $prod->nombre,
                    'stock'  => $prod->stock,
                    'minimo' => $prod->stock_minimo,
                    'agotado'=> $prod->stock <= 0,
                ];
            }
        }

        return response()->json(['ok' => true, 'data' => array_merge($result, ['alertas' => $alertas])]);
    }

    public function anular(Request $request, $id)
    {
        DB::transaction(function() use ($id) {
            $items = DB::table('Detalle_Venta')->where('id_venta', $id)->get();
            foreach ($items as $it)
                DB::table('Herramienta')->where('id_herramienta', $it->id_herramienta)->increment('stock', $it->cantidad);
            DB::table('Venta')->where('id_venta', $id)->update(['estado' => 'anulada']);
        });
        return response()->json(['ok' => true, 'msg' => 'Venta anulada']);
    }
}
