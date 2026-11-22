<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigController extends Controller
{
    public function index()
    {
        $row = DB::table('Configuracion')->where('clave','main')->first();
        $data = $row ? json_decode($row->valor, true) : ['nombre'=>'Ferretería JP Los Mellizos','ciudad'=>'Palmira'];
        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function guardar(Request $request)
    {
        $valor = json_encode($request->all());
        $ex = DB::table('Configuracion')->where('clave','main')->first();
        if ($ex) DB::table('Configuracion')->where('clave','main')->update(['valor' => $valor]);
        else     DB::table('Configuracion')->insert(['clave' => 'main', 'valor' => $valor]);
        return response()->json(['ok' => true, 'msg' => 'Configuración guardada']);
    }
}