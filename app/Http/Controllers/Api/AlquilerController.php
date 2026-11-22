<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlquilerController extends Controller
{
    public function index()
    {
        $alqs = DB::table('Alquiler as a')
            ->leftJoin('Cliente as c', 'a.id_cliente', '=', 'c.id_cliente')
            ->select('a.*', 'c.nombre as cli_nom', 'c.telefono as cli_tel')
            ->orderByDesc('a.id_alquiler')->limit(200)->get();

        $result = $alqs->map(function($a) {
            $detalles = DB::table('Detalle_Alquiler as da')
                ->leftJoin('Herramienta as h', 'da.id_herramienta', '=', 'h.id_herramienta')
                ->select('da.*', 'h.nombre as prod_nom')
                ->where('da.id_alquiler', $a->id_alquiler)->get();
            return [
                'id_alquiler' => $a->id_alquiler,
                'cliente'     => $a->cli_nom ?? $a->cliente_nom ?? '',
                'cedula'      => $a->cedula ?? '',
                'tel'         => $a->cli_tel ?? $a->telefono ?? '',
                'ini'         => $a->fecha_inicio,
                'fin'         => $a->fecha_final,
                'estado'      => $this->calcularEstado($a),
                'total'       => (float)$a->total,
                'notas'       => $a->observaciones ?? '',
                'ciudad'      => $a->ciudad ?? '',
                'direccion'   => $a->direccion ?? '',
                'barrio'      => $a->barrio ?? '',
                'lat'         => $a->lat !== null ? (float)$a->lat : null,
                'lng'         => $a->lng !== null ? (float)$a->lng : null,
                'items'       => $detalles->map(fn($d) => [
                    'prodId'  => $d->id_herramienta,
                    'prodNom' => $d->prod_nom,
                    'cant'    => (int)$d->cantidad,
                    'pd'      => (float)$d->precio_dia,
                ]),
            ];
        });
        return response()->json(['ok' => true, 'data' => $result]);
    }

    public function store(Request $request)
    {
        $items = $request->input('items', []);
        if (!count($items))
            return response()->json(['ok' => false, 'msg' => 'El alquiler debe tener al menos una herramienta'], 400);

        foreach ($items as $it) {
            if (empty($it['prodId']) || (int)($it['cant'] ?? 0) <= 0)
                return response()->json(['ok' => false, 'msg' => 'Hay una herramienta con datos inválidos'], 400);
        }

        // Validar stock antes de la transacción
        foreach ($items as $it) {
            $prod = DB::table('Herramienta')->where('id_herramienta', $it['prodId'])->first();
            $cant = (int)($it['cant'] ?? 1);
            if (!$prod || $prod->stock < $cant) {
                $disp = $prod ? $prod->stock : 0;
                $nom  = $prod ? $prod->nombre : "ID {$it['prodId']}";
                return response()->json([
                    'ok'  => false,
                    'msg' => "Stock insuficiente para «{$nom}» (disponible: {$disp}, solicitado: {$cant})"
                ], 400);
            }
        }

        $lat = is_numeric($request->input('lat')) ? (float)$request->input('lat') : null;
        $lng = is_numeric($request->input('lng')) ? (float)$request->input('lng') : null;
        $ciudad    = $request->input('ciudad', '') ?: null;
        $direccion = $request->input('direccion', '') ?: null;
        $barrio    = $request->input('barrio', '') ?: null;

        $dias = 1;
        if ($request->input('ini') && $request->input('fin')) {
            $dias = max(1, \Carbon\Carbon::parse($request->input('ini'))->diffInDays(\Carbon\Carbon::parse($request->input('fin'))));
        }

        $itemsConNombre = array_map(function($it) {
            $prod = DB::table('Herramienta')->where('id_herramienta', $it['prodId'])->first();
            return [
                'id'         => $it['prodId'],
                'nombre'     => $prod->nombre ?? '',
                'cantidad'   => (int)$it['cant'],
                'precio_dia' => (float)$it['pd'],
            ];
        }, $items);

        $result = DB::transaction(function() use ($request, $items, $itemsConNombre, $dias, $ciudad, $direccion, $barrio, $lat, $lng) {
            $cliId = $request->input('cliId');
            if (!$cliId) {
                $cliId = DB::table('Cliente')->insertGetId([
                    'nombre'    => $request->input('cliente', ''),
                    'telefono'  => $request->input('tel', ''),
                    'documento' => $request->input('cedula', ''),
                    'estado'    => 'activo',
                    'ciudad'    => $ciudad,
                    'direccion' => $direccion,
                    'lat'       => $lat,
                    'lng'       => $lng,
                ]);
            }
            $aid = DB::table('Alquiler')->insertGetId([
                'fecha_inicio'  => $request->input('ini'),
                'fecha_final'   => $request->input('fin'),
                'dias'          => $dias,
                'estado'        => 'Activo',
                'total'         => $request->input('total', 0),
                'observaciones' => $request->input('notas', ''),
                'id_usuario'    => $request->input('usuarioId', 1),
                'id_cliente'    => $cliId,
                'cliente_nom'   => $request->input('cliente', ''),
                'cedula'        => $request->input('cedula', ''),
                'telefono'      => $request->input('tel', ''),
                'ciudad'        => $ciudad,
                'direccion'     => $direccion,
                'barrio'        => $barrio,
                'lat'           => $lat,
                'lng'           => $lng,
                'items'         => json_encode($itemsConNombre),
            ]);
            foreach ($items as $it) {
                DB::table('Detalle_Alquiler')->insert([
                    'id_alquiler'    => $aid,
                    'id_herramienta' => $it['prodId'],
                    'cantidad'       => $it['cant'],
                    'precio_dia'     => $it['pd'],
                ]);
                DB::table('Herramienta')->where('id_herramienta', $it['prodId'])->decrement('stock', $it['cant']);
            }
            return ['id' => $aid];
        });

        return response()->json(['ok' => true, 'data' => $result]);
    }

    public function devolver(Request $request, $id)
    {
        DB::transaction(function() use ($id) {
            $items = DB::table('Detalle_Alquiler')->where('id_alquiler', $id)->get();
            foreach ($items as $it)
                DB::table('Herramienta')->where('id_herramienta', $it->id_herramienta)->increment('stock', $it->cantidad);
            DB::table('Alquiler')->where('id_alquiler', $id)->update(['estado' => 'Devuelto']);
        });
        return response()->json(['ok' => true, 'msg' => 'Devolución registrada']);
    }

    public function prorroga(Request $request, $id)
    {
        DB::table('Alquiler')->where('id_alquiler', $id)->update([
            'fecha_final' => $request->input('nuevaFecha'),
            'estado'      => 'Activo',
        ]);
        return response()->json(['ok' => true, 'msg' => 'Alquiler prorrogado']);
    }

    /**
     * Estados guardados en BD:
     *  - 'Activo'   → automático: "En curso" hasta la fecha final, luego "Vencido"
     *  - 'En curso' / 'Vencido' / 'En mora' → elegidos a mano, se respetan tal cual
     *  - 'Devuelto'
     */
    public const ESTADOS_ABIERTOS = ['activo', 'en curso', 'vencido', 'en mora'];

    public static function estadoVisible($estado, $fechaFinal)
    {
        $e = strtolower(trim($estado ?? ''));

        if (in_array($e, ['devuelto', 'finalizado'])) return 'Devuelto';
        if ($e === 'en curso') return 'En curso';
        if ($e === 'vencido')  return 'Vencido';
        if ($e === 'en mora')  return 'En mora';

        if ($fechaFinal && now()->toDateString() > $fechaFinal) return 'Vencido';
        return 'En curso';
    }

    /** Filtro SQL de alquileres sin devolver. */
    public static function scopeAbiertos($q)
    {
        $marcas = implode(',', array_fill(0, count(self::ESTADOS_ABIERTOS), '?'));
        return $q->whereRaw("LOWER(estado) IN ($marcas)", self::ESTADOS_ABIERTOS);
    }

    /** Filtro SQL de alquileres que se ven como "Vencido" o "En mora". */
    public static function scopeAtrasados($q)
    {
        return $q->where(fn($w) => $w
            ->whereRaw('LOWER(estado) IN (?, ?)', ['vencido', 'en mora'])
            ->orWhere(fn($a) => $a
                ->whereRaw('LOWER(estado) = ?', ['activo'])
                ->where('fecha_final', '<', now()->toDateString())));
    }

    private function calcularEstado($alquiler)
    {
        return self::estadoVisible($alquiler->estado, $alquiler->fecha_final);
    }

    public function cambiarEstado(Request $request, $id)
    {
        $estadosBD = [
            'Automatico' => 'Activo',
            'En curso'   => 'En curso',
            'Vencido'    => 'Vencido',
            'En mora'    => 'En mora',
            'Devuelto'   => 'Devuelto',
        ];
        $estadoBD = $estadosBD[$request->input('estado')] ?? null;
        if (!$estadoBD)
            return response()->json(['ok' => false, 'msg' => 'Estado no válido'], 400);

        $alq = DB::table('Alquiler')->where('id_alquiler', $id)->first();
        if (!$alq)
            return response()->json(['ok' => false, 'msg' => 'Alquiler no encontrado'], 404);

        $estabaDevuelto = self::estadoVisible($alq->estado, $alq->fecha_final) === 'Devuelto';
        $quedaDevuelto  = $estadoBD === 'Devuelto';

        // Pasar a/desde "Devuelto" mueve el stock, igual que el botón de devolver
        DB::transaction(function () use ($id, $estadoBD, $estabaDevuelto, $quedaDevuelto) {
            if ($estabaDevuelto !== $quedaDevuelto) {
                $items = DB::table('Detalle_Alquiler')->where('id_alquiler', $id)->get();
                foreach ($items as $it) {
                    $q = DB::table('Herramienta')->where('id_herramienta', $it->id_herramienta);
                    $quedaDevuelto ? $q->increment('stock', $it->cantidad) : $q->decrement('stock', $it->cantidad);
                }
            }
            DB::table('Alquiler')->where('id_alquiler', $id)->update(['estado' => $estadoBD]);
        });

        return response()->json(['ok' => true, 'msg' => 'Estado actualizado']);
    }

    public function mapa()
    {
        // Geocodificar direcciones sin lat/lng puede tardar (1 req/seg por límite de Nominatim);
        // solo ocurre la primera vez por registro, luego queda cacheado en la BD.
        set_time_limit(120);

        $activos = DB::table('Alquiler')
            ->where(fn($q) => self::scopeAbiertos($q))
            ->orderByDesc('fecha_inicio')
            ->get();

        $hoy = now()->toDateString();

        $resultado = $activos->map(function ($a) use ($hoy) {
            if (is_null($a->lat) || is_null($a->lng)) {
                $coords = $this->geocodificar($a->direccion, $a->barrio, $a->ciudad);
                if ($coords) {
                    DB::table('Alquiler')->where('id_alquiler', $a->id_alquiler)
                        ->update(['lat' => $coords['lat'], 'lng' => $coords['lng']]);
                    $a->lat = $coords['lat'];
                    $a->lng = $coords['lng'];
                }
            }

            $items = [];
            if ($a->items) {
                $decoded = json_decode($a->items, true);
                if (is_array($decoded)) $items = $decoded;
            }

            return [
                'id'           => $a->id_alquiler,
                'cliente'      => $a->cliente_nom,
                'telefono'     => $a->telefono,
                'cedula'       => $a->cedula,
                'ciudad'       => $a->ciudad,
                'barrio'       => $a->barrio,
                'direccion'    => $a->direccion,
                'lat'          => $a->lat !== null ? (float)$a->lat : null,
                'lng'          => $a->lng !== null ? (float)$a->lng : null,
                'items'        => $items,
                'total'        => (float)$a->total,
                'fecha_inicio' => $a->fecha_inicio,
                'fecha_final'  => $a->fecha_final,
                'dias'         => (int)$a->dias,
                'en_mora'      => in_array(self::estadoVisible($a->estado, $a->fecha_final), ['Vencido', 'En mora']),
            ];
        })->filter(fn($a) => $a['lat'] !== null && $a['lng'] !== null)->values();

        return response()->json(['ok' => true, 'data' => [
            'alquileres' => $resultado,
            'enMora'     => $resultado->where('en_mora', true)->pluck('id')->values(),
            'centro'     => ['lat' => 3.5394, 'lng' => -76.3026],
        ]]);
    }

    private function geocodificar($direccion, $barrio, $ciudad)
    {
        $ciudad = $ciudad ?: 'Palmira';

        // De más específico a más general: dirección exacta → barrio → solo ciudad
        $intentos = array_values(array_unique(array_filter([
            implode(', ', array_filter([$direccion, $barrio, $ciudad, 'Valle del Cauca', 'Colombia'])),
            implode(', ', array_filter([$barrio, $ciudad, 'Valle del Cauca', 'Colombia'])),
            implode(', ', array_filter([$ciudad, 'Valle del Cauca', 'Colombia'])),
        ])));

        foreach ($intentos as $query) {
            try {
                $res = \Illuminate\Support\Facades\Http::withHeaders([
                        'User-Agent' => 'FARIO-Ferreteria/1.0 (contacto@ferreteriajp.co)',
                    ])
                    ->timeout(8)
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q'            => $query,
                        'format'       => 'json',
                        'limit'        => 1,
                        'countrycodes' => 'co',
                    ]);

                // Respetar el límite de 1 solicitud/segundo de Nominatim
                usleep(1100000);

                $data = $res->json();
                if (!empty($data)) {
                    return [
                        'lat'        => (float)$data[0]['lat'],
                        'lng'        => (float)$data[0]['lon'],
                        'aproximado' => $query !== $intentos[0],
                    ];
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Geocodificación de alquiler fallida: ' . $e->getMessage());
            }
        }

        // Última opción: ubicar en el centro de Palmira con una pequeña variación
        // para que el alquiler nunca desaparezca del mapa, aunque sea aproximado.
        return [
            'lat'        => 3.5394 + (mt_rand(-400, 400) / 10000),
            'lng'        => -76.3026 + (mt_rand(-400, 400) / 10000),
            'aproximado' => true,
        ];
    }
}
