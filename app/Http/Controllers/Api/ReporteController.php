<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function dashboard(Request $request)
    {
        $periodo  = $request->input('periodo', 'mes'); // mes | semestre | anio
        $anio     = (int)$request->input('anio', now()->year);
        $mes      = (int)$request->input('mes',  now()->month);
        $semestre = (int)$request->input('semestre', now()->month <= 6 ? 1 : 2);

        // Rango de fechas del período seleccionado
        if ($periodo === 'mes') {
            $inicio = \Carbon\Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
            $fin    = \Carbon\Carbon::createFromDate($anio, $mes, 1)->endOfMonth();
            $inicioPrev = $inicio->copy()->subMonth()->startOfMonth();
            $finPrev    = $inicioPrev->copy()->endOfMonth();
        } elseif ($periodo === 'semestre') {
            $mesIni = $semestre === 1 ? 1 : 7;
            $mesFin = $semestre === 1 ? 6 : 12;
            $inicio = \Carbon\Carbon::createFromDate($anio, $mesIni, 1)->startOfMonth();
            $fin    = \Carbon\Carbon::createFromDate($anio, $mesFin, 1)->endOfMonth();
            if ($semestre === 1) {
                $inicioPrev = \Carbon\Carbon::createFromDate($anio - 1, 7, 1)->startOfMonth();
                $finPrev    = \Carbon\Carbon::createFromDate($anio - 1, 12, 1)->endOfMonth();
            } else {
                $inicioPrev = \Carbon\Carbon::createFromDate($anio, 1, 1)->startOfMonth();
                $finPrev    = \Carbon\Carbon::createFromDate($anio, 6, 1)->endOfMonth();
            }
        } else { // anio
            $inicio = \Carbon\Carbon::createFromDate($anio, 1, 1)->startOfYear();
            $fin    = \Carbon\Carbon::createFromDate($anio, 12, 31)->endOfYear();
            $inicioPrev = \Carbon\Carbon::createFromDate($anio - 1, 1, 1)->startOfYear();
            $finPrev    = \Carbon\Carbon::createFromDate($anio - 1, 12, 31)->endOfYear();
        }

        $esMesActual = ($periodo === 'mes' && $anio === (int)now()->year && $mes === (int)now()->month);
        $noAnulada   = fn($q) => $q->whereNull('estado')->orWhere('estado', '!=', 'anulada');

        // Ventas hoy — siempre en tiempo real
        $ventasHoyQ = DB::table('Venta')
            ->whereDate('fecha', now()->toDateString())
            ->where($noAnulada);

        // Total ventas del período (Ferretería)
        $totalVentas = (float)DB::table('Venta')
            ->whereBetween('fecha', [$inicio, $fin])
            ->where($noAnulada)
            ->sum('total');

        // Total alquileres del período
        $totalAlquileres = (float)DB::table('Alquiler')
            ->whereBetween('fecha_inicio', [$inicio, $fin])
            ->sum('total');

        $totalCombinado = $totalVentas + $totalAlquileres;

        // Período anterior
        $totalVentasPrev = (float)DB::table('Venta')
            ->whereBetween('fecha', [$inicioPrev, $finPrev])
            ->where($noAnulada)
            ->sum('total');

        // Ventas por día: últimos 7 días si es mes actual, todo el período si no
        if ($esMesActual) {
            $ventasPorDia = DB::table('Venta')
                ->selectRaw('DATE(fecha) as dia, COUNT(*) as cantidad, SUM(total) as total')
                ->whereBetween('fecha', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
                ->where($noAnulada)
                ->groupBy('dia')->orderBy('dia')->get();
        } else {
            $ventasPorDia = DB::table('Venta')
                ->selectRaw('DATE(fecha) as dia, COUNT(*) as cantidad, SUM(total) as total')
                ->whereBetween('fecha', [$inicio, $fin])
                ->where($noAnulada)
                ->groupBy('dia')->orderBy('dia')->get();
        }

        // Ventas por método de pago
        $ventasPorMetodo = DB::table('Venta')
            ->selectRaw('metodo_pago, COUNT(*) as cantidad, SUM(total) as total')
            ->whereBetween('fecha', [$inicio, $fin])
            ->where($noAnulada)
            ->groupBy('metodo_pago')->get();

        // Top 5 productos más vendidos
        $topProductos = DB::table('Detalle_Venta as dv')
            ->join('Herramienta as h', 'dv.id_herramienta', '=', 'h.id_herramienta')
            ->join('Venta as v', 'dv.id_venta', '=', 'v.id_venta')
            ->selectRaw('h.nombre, SUM(dv.cantidad) as total_cant, SUM(dv.precio*dv.cantidad) as total_val')
            ->whereBetween('v.fecha', [$inicio, $fin])
            ->where(fn($q) => $q->whereNull('v.estado')->orWhere('v.estado', '!=', 'anulada'))
            ->groupBy('h.nombre')->orderByDesc('total_cant')->limit(5)->get();

        // Top 5 clientes · Ferretería
        $topClientes = DB::table('Venta as v')
            ->join('Cliente as c', 'v.id_cliente', '=', 'c.id_cliente')
            ->selectRaw('c.nombre, COUNT(*) as compras, SUM(v.total) as total')
            ->whereBetween('v.fecha', [$inicio, $fin])
            ->where(fn($q) => $q->whereNull('v.estado')->orWhere('v.estado', '!=', 'anulada'))
            ->groupBy('c.nombre')->orderByDesc('total')->limit(5)->get();

        // Top 5 clientes · Alquiler
        $topClientesAlquiler = DB::table('Alquiler as a')
            ->join('Cliente as c', 'a.id_cliente', '=', 'c.id_cliente')
            ->selectRaw('c.nombre, COUNT(*) as alquileres, SUM(a.total) as total')
            ->whereBetween('a.fecha_inicio', [$inicio, $fin])
            ->whereNotNull('a.id_cliente')
            ->groupBy('c.nombre')->orderByDesc('total')->limit(5)->get();

        // Top 3 vendedores (usuarios) por ventas
        $topVendedores = DB::table('Venta as v')
            ->join('Usuario as u', 'v.id_usuario', '=', 'u.id_usuario')
            ->selectRaw('u.nombre, COUNT(*) as cantidad, SUM(v.total) as total')
            ->whereBetween('v.fecha', [$inicio, $fin])
            ->where(fn($q) => $q->whereNull('v.estado')->orWhere('v.estado', '!=', 'anulada'))
            ->groupBy('u.nombre')->orderByDesc('total')->limit(3)->get();

        // Top 3 proveedores por compras
        $topProveedores = DB::table('Compra as co')
            ->join('Proveedor as p', 'co.id_proveedor', '=', 'p.id_proveedor')
            ->selectRaw('p.nombre, p.empresa, COUNT(*) as compras, SUM(co.total) as total')
            ->whereBetween('co.fecha', [$inicio, $fin])
            ->groupBy('p.nombre', 'p.empresa')->orderByDesc('total')->limit(3)->get();

        // Ventas por día de semana
        $ventasPorDiaSemana = DB::table('Venta')
            ->selectRaw('DAYOFWEEK(fecha) as dia_semana, COUNT(*) as cantidad, SUM(total) as total')
            ->whereBetween('fecha', [$inicio, $fin])
            ->where($noAnulada)
            ->groupBy('dia_semana')->orderBy('dia_semana')->get();

        // Alertas en tiempo real
        $alqActivoRaw = fn($q) => AlquilerController::scopeAbiertos($q);

        $alqPorVencer = DB::table('Alquiler')
            ->whereBetween('fecha_final', [now()->toDateString(), now()->addDay()->toDateString()])
            ->where($alqActivoRaw)->count();

        $alqEnMora = DB::table('Alquiler')
            ->where(fn($q) => AlquilerController::scopeAtrasados($q))->count();

        $sinMovimiento = DB::table('Herramienta as h')
            ->whereNotIn('h.id_herramienta', function($q) {
                $q->select('dv.id_herramienta')
                  ->from('Detalle_Venta as dv')
                  ->join('Venta as v', 'dv.id_venta', '=', 'v.id_venta')
                  ->where('v.fecha', '>=', now()->subDays(30));
            })
            ->where('h.stock', '>', 0)->count();

        return response()->json(['ok' => true, 'data' => [
            'ventasHoy'           => (float)$ventasHoyQ->sum('total'),
            'ventasHoyN'          => $ventasHoyQ->count(),
            'ventasMes'           => $totalVentas,
            'ventasMesAnterior'   => $totalVentasPrev,
            'totalAlquileres'     => $totalAlquileres,
            'totalCombinado'      => $totalCombinado,
            'pctFerreteria'       => $totalCombinado > 0 ? round($totalVentas / $totalCombinado * 100, 1) : 0,
            'pctAlquiler'         => $totalCombinado > 0 ? round($totalAlquileres / $totalCombinado * 100, 1) : 0,
            'alqActivos'          => DB::table('Alquiler')->where($alqActivoRaw)->count(),
            'alqPorVencer'        => $alqPorVencer,
            'alqEnMora'           => $alqEnMora,
            'stockBajo'           => DB::table('Herramienta')->whereRaw('stock <= stock_minimo AND stock > 0')->count(),
            'agotado'             => DB::table('Herramienta')->where('stock', '<=', 0)->count(),
            'clientes'            => DB::table('Cliente')->where('estado', 'activo')->count(),
            'sinMovimiento'       => $sinMovimiento,
            'esMesActual'         => $esMesActual,
            'ventasPorDia'        => $ventasPorDia,
            'ventasPorMetodo'     => $ventasPorMetodo,
            'topProductos'        => $topProductos,
            'topClientes'         => $topClientes,
            'topClientesAlquiler' => $topClientesAlquiler,
            'topVendedores'       => $topVendedores,
            'topProveedores'      => $topProveedores,
            'ventasPorDiaSemana'  => $ventasPorDiaSemana,
            'periodo'             => $periodo,
            'semestre'            => $semestre,
        ]]);
    }

    public function ventas(Request $request)
    {
        $tipo  = $request->input('tipo', 'mes');
        $fecha = $request->input('fecha', now()->format('Y-m'));

        $query = DB::table('Venta as v')
            ->leftJoin('Cliente as c', 'v.id_cliente', '=', 'c.id_cliente')
            ->select('v.id_venta','v.fecha','c.nombre as cliente','v.metodo_pago','v.total')
            ->where(fn($q) => $q->whereNull('v.estado')->orWhere('v.estado','!=','anulada'));

        if ($tipo === 'dia')       $query->whereDate('v.fecha', $fecha);
        elseif ($tipo === 'mes')   $query->whereYear('v.fecha', substr($fecha,0,4))->whereMonth('v.fecha', substr($fecha,5,2));
        else                       $query->whereYear('v.fecha', $fecha);

        $ventas = $query->orderByDesc('v.fecha')->get();
        $total  = $ventas->sum('total');

        $top = DB::table('Detalle_Venta as dv')
            ->join('Herramienta as h', 'dv.id_herramienta','=','h.id_herramienta')
            ->join('Venta as v', 'dv.id_venta','=','v.id_venta')
            ->select('h.nombre', DB::raw('SUM(dv.cantidad) as total_cant'), DB::raw('SUM(dv.precio*dv.cantidad) as total_val'))
            ->groupBy('h.nombre')->orderByDesc('total_cant')->limit(5)->get();

        return response()->json(['ok' => true, 'data' => [
            'ventas'   => $ventas,
            'total'    => (float)$total,
            'cantidad' => $ventas->count(),
            'top'      => $top,
        ]]);
    }
    public function ventasPorMes(Request $request)
{
    $anio = $request->input('anio', now()->year);

    $meses = DB::table('Venta')
        ->selectRaw('MONTH(fecha) as mes, COUNT(*) as cantidad, SUM(total) as total')
        ->whereYear('fecha', $anio)
        ->where(fn($q) => $q->whereNull('estado')->orWhere('estado','!=','anulada'))
        ->groupBy('mes')
        ->orderBy('mes')
        ->get();

    // Llenar los 12 meses aunque no tengan ventas
    $resultado = [];
    for ($i = 1; $i <= 12; $i++) {
        $mes = $meses->firstWhere('mes', $i);
        $resultado[] = [
            'mes'      => $i,
            'nombre'   => now()->setMonth($i)->locale('es')->monthName,
            'cantidad' => $mes ? (int)$mes->cantidad : 0,
            'total'    => $mes ? (float)$mes->total  : 0,
        ];
    }

    // Detalle del mes seleccionado
    $mesSeleccionado = (int)$request->input('mes', now()->month);
    $detalle = DB::table('Venta as v')
        ->leftJoin('Cliente as c', 'v.id_cliente','=','c.id_cliente')
        ->select('v.id_venta','v.fecha','c.nombre as cliente','v.metodo_pago','v.total')
        ->whereYear('v.fecha', $anio)
        ->whereMonth('v.fecha', $mesSeleccionado)
        ->where(fn($q) => $q->whereNull('v.estado')->orWhere('v.estado','!=','anulada'))
        ->orderByDesc('v.fecha')
        ->get()
        ->map(fn($v) => [
            'id_venta'    => $v->id_venta,
            'fecha'       => $v->fecha,
            'cliente'     => $v->cliente ?? 'Consumidor Final',
            'metodo_pago' => $v->metodo_pago,
            'total'       => (float)$v->total,
        ]);

    $topMes = DB::table('Detalle_Venta as dv')
        ->join('Herramienta as h','dv.id_herramienta','=','h.id_herramienta')
        ->join('Venta as v','dv.id_venta','=','v.id_venta')
        ->selectRaw('h.nombre, SUM(dv.cantidad) as total_cant, SUM(dv.precio*dv.cantidad) as total_val')
        ->whereYear('v.fecha', $anio)
        ->whereMonth('v.fecha', $mesSeleccionado)
        ->where(fn($q) => $q->whereNull('v.estado')->orWhere('v.estado','!=','anulada'))
        ->groupBy('h.nombre')
        ->orderByDesc('total_cant')
        ->limit(5)
        ->get();

    return response()->json(['ok' => true, 'data' => [
        'meses'    => $resultado,
        'detalle'  => $detalle,
        'topMes'   => $topMes,
        'anio'     => (int)$anio,
        'mesActual'=> $mesSeleccionado,
    ]]);
}

    public function analisis(Request $request)
    {
        $periodo = $request->input('periodo', 'anio');
        $anio    = (int)$request->input('anio', now()->year);
        $mes     = (int)$request->input('mes', now()->month);

        $noAnulada = fn($q) => $q->whereNull('v.estado')->orWhere('v.estado', '!=', 'anulada');

        // Top 10 productos más vendidos
        $qProd = DB::table('Detalle_Venta as dv')
            ->join('Herramienta as h', 'dv.id_herramienta', '=', 'h.id_herramienta')
            ->join('Venta as v', 'dv.id_venta', '=', 'v.id_venta')
            ->selectRaw('h.nombre, SUM(dv.cantidad) as total_cant, SUM(dv.precio * dv.cantidad) as total_val')
            ->where($noAnulada);
        if ($periodo === 'mes')  $qProd->whereYear('v.fecha', $anio)->whereMonth('v.fecha', $mes);
        if ($periodo === 'anio') $qProd->whereYear('v.fecha', $anio);
        $topProductos = $qProd->groupBy('h.nombre')->orderByDesc('total_cant')->limit(10)->get();

        // Top 10 herramientas más alquiladas
        $qAlq = DB::table('Detalle_Alquiler as da')
            ->join('Herramienta as h', 'da.id_herramienta', '=', 'h.id_herramienta')
            ->join('Alquiler as a', 'da.id_alquiler', '=', 'a.id_alquiler')
            ->selectRaw('h.nombre, COUNT(DISTINCT da.id_alquiler) as total_alquileres, SUM(da.cantidad) as total_cant');
        if ($periodo === 'mes')  $qAlq->whereYear('a.fecha_inicio', $anio)->whereMonth('a.fecha_inicio', $mes);
        if ($periodo === 'anio') $qAlq->whereYear('a.fecha_inicio', $anio);
        $topAlquiladas = $qAlq->groupBy('h.nombre')->orderByDesc('total_alquileres')->limit(10)->get();

        // Ventas por método de pago
        $qMetodo = DB::table('Venta')
            ->selectRaw('metodo_pago, COUNT(*) as cantidad, SUM(total) as total')
            ->where(fn($q) => $q->whereNull('estado')->orWhere('estado', '!=', 'anulada'));
        if ($periodo === 'mes')  $qMetodo->whereYear('fecha', $anio)->whereMonth('fecha', $mes);
        if ($periodo === 'anio') $qMetodo->whereYear('fecha', $anio);
        $porMetodo = $qMetodo->groupBy('metodo_pago')->orderByDesc('total')->get();

        // Top 5 clientes por gasto
        $qCli = DB::table('Venta as v')
            ->join('Cliente as c', 'v.id_cliente', '=', 'c.id_cliente')
            ->selectRaw('c.nombre, COUNT(v.id_venta) as compras, SUM(v.total) as total')
            ->where($noAnulada);
        if ($periodo === 'mes')  $qCli->whereYear('v.fecha', $anio)->whereMonth('v.fecha', $mes);
        if ($periodo === 'anio') $qCli->whereYear('v.fecha', $anio);
        $topClientes = $qCli->groupBy('c.nombre')->orderByDesc('total')->limit(5)->get();

        // Totales del período
        $qTotalV = DB::table('Venta')
            ->where(fn($q) => $q->whereNull('estado')->orWhere('estado', '!=', 'anulada'));
        if ($periodo === 'mes')  $qTotalV->whereYear('fecha', $anio)->whereMonth('fecha', $mes);
        if ($periodo === 'anio') $qTotalV->whereYear('fecha', $anio);

        $qTotalA = DB::table('Alquiler');
        if ($periodo === 'mes')  $qTotalA->whereYear('fecha_inicio', $anio)->whereMonth('fecha_inicio', $mes);
        if ($periodo === 'anio') $qTotalA->whereYear('fecha_inicio', $anio);

        return response()->json(['ok' => true, 'data' => [
            'topProductos'    => $topProductos,
            'topAlquiladas'   => $topAlquiladas,
            'porMetodo'       => $porMetodo,
            'topClientes'     => $topClientes,
            'totalVentas'     => (float)$qTotalV->sum('total'),
            'totalAlquileres' => (float)$qTotalA->sum('total'),
        ]]);
    }
}