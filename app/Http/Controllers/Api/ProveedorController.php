<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProveedorController extends Controller
{
    public function index()
    {
        $rows = DB::table('Proveedor')
            ->where(fn($q) => $q->whereNull('estado')->orWhere('estado', '!=', 'inactivo'))
            ->orderBy('nombre')->get();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        if (!trim($request->input('nombre', '')))
            return response()->json(['ok' => false, 'msg' => 'El nombre del proveedor es obligatorio'], 422);

        $id = DB::table('Proveedor')->insertGetId([
            'nombre'    => $request->input('nombre'),
            'empresa'   => $request->input('empresa', ''),
            'telefono'  => $request->input('telefono', ''),
            'correo'    => $request->input('correo', ''),
            'direccion' => $request->input('direccion', ''),
            'nit'       => $request->input('nit', ''),
            'contacto'  => $request->input('contacto', $request->input('nombre')),
        ]);
        return response()->json(['ok' => true, 'data' => ['id' => $id]]);
    }

    public function update(Request $request, $id)
    {
        DB::table('Proveedor')->where('id_proveedor', $id)->update([
            'nombre'    => $request->input('nombre'),
            'empresa'   => $request->input('empresa', ''),
            'telefono'  => $request->input('telefono', ''),
            'correo'    => $request->input('correo', ''),
            'direccion' => $request->input('direccion', ''),
            'nit'       => $request->input('nit', ''),
            'contacto'  => $request->input('contacto', $request->input('nombre')),
        ]);
        return response()->json(['ok' => true, 'msg' => 'Proveedor actualizado']);
    }

    public function destroy($id)
    {
        // Soft-delete: un proveedor con compras registradas no se puede borrar
        // físicamente sin romper esos registros (hay FK reales en la BD).
        DB::table('Proveedor')->where('id_proveedor', $id)->update(['estado' => 'inactivo']);
        return response()->json(['ok' => true, 'msg' => 'Eliminado']);
    }
}