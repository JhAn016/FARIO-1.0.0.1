<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function index()
    {
        $rows = DB::table('Herramienta as h')
            ->leftJoin('Categoria as c', 'h.id_categoria', '=', 'c.id_categoria')
            ->select('h.*', 'c.nombre as categoria_nombre')
            ->where(fn($q) => $q->whereNull('h.estado')->orWhere('h.estado', '!=', 'inactivo'))
            ->orderBy('h.nombre')->get();

        $result = $rows->map(function($r) {
            $pc = (float)($r->precio_compra ?? 0);
            return [
                'id'      => $r->id_herramienta,
                'ref'     => $r->referencia ?? 'H-'.str_pad($r->id_herramienta, 3, '0', STR_PAD_LEFT),
                'nombre'  => $r->nombre,
                'desc'    => $r->descripcion ?? '',
                'cat'     => $r->categoria_nombre ?? '',
                'pcompra' => $pc,
                'p1'      => (float)($r->precio_p1 ?? $pc * 1.4),
                'p2'      => (float)($r->precio_p2 ?? $pc * 1.3),
                'p3'      => (float)($r->precio_p3 ?? $pc * 1.2),
                'palq'    => (float)($r->precio_alquiler ?? 0),
                'stock'   => (int)($r->stock ?? 0),
                'smin'    => (int)($r->stock_minimo ?? 0),
            ];
        });
        return response()->json(['ok' => true, 'data' => $result]);
    }

    public function store(Request $request)
    {
        if (!trim($request->input('nombre', '')))
            return response()->json(['ok' => false, 'msg' => 'El nombre del producto es obligatorio'], 422);

        $catId = $this->getOrCreateCategoria($request->input('cat', 'General'));
        $id = DB::table('Herramienta')->insertGetId([
            'referencia'      => $request->input('ref', ''),
            'nombre'          => $request->input('nombre'),
            'descripcion'     => $request->input('desc', ''),
            'precio_compra'   => $request->input('pcompra', 0),
            'precio_p1'       => $request->input('p1', 0),
            'precio_p2'       => $request->input('p2', 0),
            'precio_p3'       => $request->input('p3', 0),
            'precio_alquiler' => $request->input('palq', 0),
            'stock'           => $request->input('stock', 0),
            'stock_minimo'    => $request->input('smin', 0),
            'fecha_registro'  => now(),
            'id_categoria'    => $catId,
        ]);
        return response()->json(['ok' => true, 'data' => ['id' => $id]]);
    }

    public function update(Request $request, $id)
    {
        $catId = $this->getOrCreateCategoria($request->input('cat', 'General'));
        DB::table('Herramienta')->where('id_herramienta', $id)->update([
            'referencia'      => $request->input('ref', ''),
            'nombre'          => $request->input('nombre'),
            'descripcion'     => $request->input('desc', ''),
            'precio_compra'   => $request->input('pcompra', 0),
            'precio_p1'       => $request->input('p1', 0),
            'precio_p2'       => $request->input('p2', 0),
            'precio_p3'       => $request->input('p3', 0),
            'precio_alquiler' => $request->input('palq', 0),
            'stock'           => $request->input('stock', 0),
            'stock_minimo'    => $request->input('smin', 0),
            'id_categoria'    => $catId,
        ]);
        return response()->json(['ok' => true, 'msg' => 'Producto actualizado']);
    }

    public function destroy($id)
    {
        // Soft-delete: un producto con historial de ventas/compras/alquileres no se
        // puede borrar físicamente sin romper esos registros (hay FK reales en la BD).
        DB::table('Herramienta')->where('id_herramienta', $id)->update(['estado' => 'inactivo']);
        return response()->json(['ok' => true, 'msg' => 'Eliminado']);
    }

    public function ajustarStock(Request $request, $id)
    {
        $delta = (int)$request->input('delta', 0);
        DB::table('Herramienta')->where('id_herramienta', $id)->increment('stock', $delta);
        $prod = DB::table('Herramienta')->where('id_herramienta', $id)->first();
        return response()->json(['ok' => true, 'data' => ['stock' => $prod->stock]]);
    }

    public function stockCritico()
    {
        $rows = DB::table('Herramienta')
            ->whereRaw('stock <= stock_minimo')
            ->select('id_herramienta as id', 'nombre', 'stock', 'stock_minimo')
            ->orderBy('stock')->get();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function movimientos($id)
    {
        $ventas = DB::table('Detalle_Venta as dv')
            ->join('Venta as v', 'dv.id_venta', '=', 'v.id_venta')
            ->where('dv.id_herramienta', $id)
            ->where(fn($q) => $q->whereNull('v.estado')->orWhere('v.estado', '!=', 'anulada'))
            ->select(
                'v.fecha', 'dv.cantidad',
                DB::raw("'Venta' as tipo"),
                DB::raw("CONCAT('Venta ', IFNULL(v.numero_factura, CONCAT('#', v.id_venta))) as referencia"),
                DB::raw("'-' as signo")
            )
            ->get();

        $compras = DB::table('Detalle_Compra as dc')
            ->join('Compra as c', 'dc.id_compra', '=', 'c.id_compra')
            ->where('dc.id_herramienta', $id)
            ->select(
                'c.fecha', 'dc.cantidad',
                DB::raw("'Compra' as tipo"),
                DB::raw("CONCAT('Compra #', c.id_compra) as referencia"),
                DB::raw("'+' as signo")
            )
            ->get();

        $alquileres = DB::table('Detalle_Alquiler as da')
            ->join('Alquiler as a', 'da.id_alquiler', '=', 'a.id_alquiler')
            ->where('da.id_herramienta', $id)
            ->select(
                'a.fecha_inicio as fecha', 'da.cantidad',
                DB::raw("'Alquiler' as tipo"),
                DB::raw("CONCAT('Alquiler #', a.id_alquiler, ' (', a.estado, ')') as referencia"),
                DB::raw("'-' as signo")
            )
            ->get();

        $movimientos = $ventas->concat($compras)->concat($alquileres)
            ->sortByDesc('fecha')
            ->values();

        return response()->json(['ok' => true, 'data' => $movimientos]);
    }

    private function getOrCreateCategoria($nombre)
    {
        $cat = DB::table('Categoria')->where('nombre', $nombre)->first();
        if ($cat) return $cat->id_categoria;
        return DB::table('Categoria')->insertGetId(['nombre' => $nombre]);
    }
}