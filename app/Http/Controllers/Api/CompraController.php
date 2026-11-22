<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index()
    {
        $compras = DB::table('Compra as c')
            ->leftJoin('Proveedor as p','c.id_proveedor','=','p.id_proveedor')
            ->select('c.*','p.nombre as proveedor_nombre')
            ->orderByDesc('c.id_compra')->limit(200)->get();

        $result = $compras->map(function($c) {
            $detalles = DB::table('Detalle_Compra as dc')
                ->leftJoin('Herramienta as h','dc.id_herramienta','=','h.id_herramienta')
                ->select('dc.*','h.nombre as prod_nombre')
                ->where('dc.id_compra',$c->id_compra)->get();
            return [
                'id_compra'    => $c->id_compra,
                'fecha'        => $c->fecha,
                'proveedor'    => $c->proveedor_nombre ?? '—',
                'id_proveedor' => $c->id_proveedor,
                'total'        => (float)$c->total,
                'observaciones'=> $c->observaciones ?? '',
                'items'        => $detalles->map(fn($d) => [
                    'id'      => $d->id_herramienta,
                    'nombre'  => $d->prod_nombre,
                    'cantidad'=> (int)$d->cantidad,
                    'precio'  => (float)$d->precio,
                    'sub'     => (float)$d->precio * (int)$d->cantidad,
                ]),
            ];
        });
        return response()->json(['ok'=>true,'data'=>$result]);
    }

    public function store(Request $request)
    {
        $items = $request->input('items',[]);
        if (!count($items))
            return response()->json(['ok'=>false,'msg'=>'La compra debe tener al menos un producto'],400);

        foreach ($items as $it) {
            if (empty($it['id']) || (int)($it['cantidad'] ?? 0) <= 0 || !is_numeric($it['precio'] ?? null))
                return response()->json(['ok'=>false,'msg'=>'Hay un producto en la compra con datos inválidos'],400);
        }

        $result = DB::transaction(function() use ($request, $items) {
            $total = array_sum(array_map(fn($i) => ($i['precio']??0) * ($i['cantidad']??1), $items));
            $cid = DB::table('Compra')->insertGetId([
                'fecha'         => now(),
                'total'         => $total,
                'id_proveedor'  => $request->input('id_proveedor',1),
                'id_usuario'    => $request->input('usuarioId',1),
                'observaciones' => $request->input('observaciones',''),
            ]);
            foreach ($items as $it) {
                DB::table('Detalle_Compra')->insert([
                    'id_compra'      => $cid,
                    'id_herramienta' => $it['id'],
                    'cantidad'       => $it['cantidad'],
                    'precio'         => $it['precio'],
                ]);
                DB::table('Herramienta')->where('id_herramienta',$it['id'])->increment('stock',$it['cantidad']);
            }
            return ['id' => $cid, 'total' => $total];
        });

        return response()->json(['ok'=>true,'data'=>$result]);
    }
}
