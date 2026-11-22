<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    public function index()
    {
        $rows = DB::table('Cliente')->where('estado', '!=', 'eliminado')->orWhereNull('estado')->orderBy('nombre')->get();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        if (!$request->input('nombre'))
            return response()->json(['ok' => false, 'msg' => 'El nombre es obligatorio'], 422);

        try {
            $data = [
                'nombre'   => trim($request->input('nombre')),
                'tipo'     => $request->input('tipo', 'Final'),
                'telefono' => $request->input('telefono', '') ?: '',
                'estado'   => 'activo',
            ];

            // Campos opcionales — solo incluir si la columna podría existir
            $opcionales = [
                'correo'    => $request->input('correo', '') ?: null,
                'direccion' => $request->input('direccion') ? mb_substr($request->input('direccion'), 0, 149) : null,
                'documento' => $request->input('cedula') ?: null,  // null evita UNIQUE duplicados
                'notas'     => $request->input('notas') ?: null,
                'ciudad'    => $request->input('ciudad') ?: null,
                'lat'       => is_numeric($request->input('lat')) ? (float)$request->input('lat') : null,
                'lng'       => is_numeric($request->input('lng')) ? (float)$request->input('lng') : null,
            ];

            // Agregar solo los opcionales cuya columna exista en la tabla
            $columnas = \Illuminate\Support\Facades\Schema::getColumnListing('Cliente');
            foreach ($opcionales as $col => $val) {
                if (in_array($col, $columnas)) $data[$col] = $val;
            }

            $id = DB::table('Cliente')->insertGetId($data);
            return response()->json(['ok' => true, 'data' => ['id' => $id]]);

        } catch (\Exception $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'duplicate')
                ? 'Ya existe un cliente con ese documento/cédula'
                : 'Error al guardar el cliente: ' . $e->getMessage();
            return response()->json(['ok' => false, 'msg' => $msg], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $data = [
                'nombre'   => trim($request->input('nombre')),
                'tipo'     => $request->input('tipo', 'Final'),
                'telefono' => $request->input('telefono', '') ?: '',
            ];

            $opcionales = [
                'correo'    => $request->input('correo', '') ?: null,
                'direccion' => $request->input('direccion') ? mb_substr($request->input('direccion'), 0, 149) : null,
                'documento' => $request->input('cedula') ?: null,
                'notas'     => $request->input('notas') ?: null,
                'ciudad'    => $request->input('ciudad') ?: null,
                'lat'       => is_numeric($request->input('lat')) ? (float)$request->input('lat') : null,
                'lng'       => is_numeric($request->input('lng')) ? (float)$request->input('lng') : null,
            ];

            $columnas = \Illuminate\Support\Facades\Schema::getColumnListing('Cliente');
            foreach ($opcionales as $col => $val) {
                if (in_array($col, $columnas)) $data[$col] = $val;
            }

            DB::table('Cliente')->where('id_cliente', $id)->update($data);
            return response()->json(['ok' => true, 'msg' => 'Cliente actualizado']);

        } catch (\Exception $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'duplicate')
                ? 'Ya existe un cliente con ese documento/cédula'
                : 'Error al actualizar: ' . $e->getMessage();
            return response()->json(['ok' => false, 'msg' => $msg], 500);
        }
    }

    public function destroy($id)
    {
        DB::table('Cliente')->where('id_cliente', $id)->update(['estado' => 'eliminado']);
        return response()->json(['ok' => true, 'msg' => 'Cliente eliminado']);
    }

    public function mapa()
    {
        $clientes = DB::table('Cliente as c')
            ->leftJoin('Venta as v', 'c.id_cliente', '=', 'v.id_cliente')
            ->select(
                'c.id_cliente', 'c.nombre', 'c.telefono',
                'c.ciudad', 'c.direccion', 'c.lat', 'c.lng',
                DB::raw('COUNT(v.id_venta) as total_compras'),
                DB::raw('COALESCE(SUM(v.total), 0) as total_gastado')
            )
            ->where(fn($q) => $q->where('c.estado', 'activo')->orWhereNull('c.estado'))
            ->groupBy('c.id_cliente','c.nombre','c.telefono','c.ciudad','c.direccion','c.lat','c.lng')
            ->orderByDesc('total_gastado')
            ->get()
            ->map(function($c) {
                $lat = $c->lat ?? (3.5394 + (mt_rand(-500, 500) / 10000));
                $lng = $c->lng ?? (-76.3026 + (mt_rand(-500, 500) / 10000));

                return [
                    'id'            => $c->id_cliente,
                    'nombre'        => $c->nombre,
                    'telefono'      => $c->telefono ?? '—',
                    'ciudad'        => $c->ciudad ?? 'Palmira',
                    'direccion'     => $c->direccion ?? '',
                    'lat'           => (float)$lat,
                    'lng'           => (float)$lng,
                    'total_compras' => (int)$c->total_compras,
                    'total_gastado' => (float)$c->total_gastado,
                    'tipo'          => $this->clasificarCliente($c->total_compras, $c->total_gastado),
                ];
            });

        $enMora = DB::table('Alquiler')
            ->where(fn($q) => AlquilerController::scopeAtrasados($q))
            ->pluck('id_cliente')
            ->toArray();

        return response()->json(['ok' => true, 'data' => [
            'clientes' => $clientes,
            'enMora'   => $enMora,
            'centro'   => ['lat' => 3.5394, 'lng' => -76.3026],
        ]]);
    }

    public function historial($id)
    {
        $ventas = DB::table('Venta as v')
            ->where('v.id_cliente', $id)
            ->where(fn($q) => $q->whereNull('v.estado')->orWhere('v.estado', '!=', 'anulada'))
            ->orderByDesc('v.fecha')
            ->get()
            ->map(function($v) {
                $items = DB::table('Detalle_Venta as dv')
                    ->join('Herramienta as h', 'dv.id_herramienta', '=', 'h.id_herramienta')
                    ->where('dv.id_venta', $v->id_venta)
                    ->select('h.nombre', 'dv.cantidad', 'dv.precio')
                    ->get();
                return [
                    'tipo'           => 'Venta',
                    'fecha'          => $v->fecha,
                    'numero_factura' => $v->numero_factura ?? "#$v->id_venta",
                    'total'          => (float)$v->total,
                    'pago'           => $v->metodo_pago,
                    'items'          => $items,
                ];
            });

        $alquileres = DB::table('Alquiler as a')
            ->where('a.id_cliente', $id)
            ->orderByDesc('a.fecha_inicio')
            ->get()
            ->map(function($a) {
                $items = DB::table('Detalle_Alquiler as da')
                    ->join('Herramienta as h', 'da.id_herramienta', '=', 'h.id_herramienta')
                    ->where('da.id_alquiler', $a->id_alquiler)
                    ->select('h.nombre', 'da.cantidad', 'da.precio_dia')
                    ->get();
                return [
                    'tipo'   => 'Alquiler',
                    'fecha'  => $a->fecha_inicio,
                    'fin'    => $a->fecha_final,
                    'estado' => $a->estado,
                    'total'  => (float)$a->total,
                    'items'  => $items,
                ];
            });

        $resumen = [
            'total_ventas'     => $ventas->count(),
            'total_gastado'    => $ventas->sum('total'),
            'total_alquileres' => $alquileres->count(),
        ];

        return response()->json(['ok' => true, 'data' => [
            'ventas'     => $ventas,
            'alquileres' => $alquileres,
            'resumen'    => $resumen,
        ]]);
    }

    private function clasificarCliente($compras, $gastado)
    {
        if ($compras >= 5 || $gastado >= 1000000) return 'frecuente';
        if ($compras >= 2) return 'regular';
        return 'nuevo';
    }
}