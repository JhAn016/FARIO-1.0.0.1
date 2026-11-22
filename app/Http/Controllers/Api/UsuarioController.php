<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function index()
    {
        $rows = DB::table('Usuario')
            ->select('id_usuario','cedula','nombre','correo','rol','estado','telefono')
            ->orderBy('id_usuario')->get();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        if (!$request->input('nombre'))
            return response()->json(['ok'=>false,'msg'=>'El nombre es obligatorio'],400);

        if (!$request->input('contrasena'))
            return response()->json(['ok'=>false,'msg'=>'La contraseña es obligatoria'],400);

        $correo = $request->input('correo','');
        if ($correo) {
            $existe = DB::table('Usuario')->where('correo',$correo)->first();
            if ($existe)
                return response()->json(['ok'=>false,'msg'=>'El correo ya está registrado'],409);
        }

        $id = DB::table('Usuario')->insertGetId([
            'cedula'     => $request->input('cedula',''),
            'nombre'     => $request->input('nombre'),
            'correo'     => $correo,
            'contrasena' => Hash::make($request->input('contrasena')),
            'rol'        => $request->input('rol','Vendedor'),
            'estado'     => 'activo',
            'telefono'   => $request->input('telefono',''),
        ]);
        return response()->json(['ok'=>true,'data'=>['id'=>$id]]);
    }

    public function update(Request $request, $id)
    {
        $data = [
            'cedula'   => $request->input('cedula',''),
            'nombre'   => $request->input('nombre'),
            'correo'   => $request->input('correo',''),
            'rol'      => $request->input('rol','Vendedor'),
            'telefono' => $request->input('telefono',''),
        ];

        // Solo encriptar si se envió nueva contraseña
        if ($request->input('contrasena'))
            $data['contrasena'] = Hash::make($request->input('contrasena'));

        DB::table('Usuario')->where('id_usuario',$id)->update($data);
        return response()->json(['ok'=>true,'msg'=>'Usuario actualizado']);
    }

    public function destroy($id)
    {
        DB::table('Usuario')->where('id_usuario',$id)->update(['estado'=>'inactivo']);
        return response()->json(['ok'=>true,'msg'=>'Usuario desactivado']);
    }
}