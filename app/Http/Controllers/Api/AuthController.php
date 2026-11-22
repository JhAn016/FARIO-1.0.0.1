<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $usuario  = trim($request->input('usuario', ''));
        $password = trim($request->input('password', ''));

        if (!$usuario || !$password)
            return response()->json(['ok' => false, 'msg' => 'Usuario y contraseña requeridos'], 400);

        $user = DB::table('Usuario')
            ->where(function($q) use ($usuario) {
                $q->where('correo', $usuario)->orWhere('nombre', $usuario);
            })
            ->where('estado', 'activo')
            ->first();

        if (!$user)
            return response()->json(['ok' => false, 'msg' => 'Usuario o contraseña incorrectos'], 401);

        // Verificar contraseña — soporta bcrypt Y texto plano (migración gradual)
        $passwordValida = false;
        if (Hash::needsRehash($user->contrasena)) {
            // Contraseña en texto plano — comparar directo
            if ($user->contrasena === $password) {
                $passwordValida = true;
                // Encriptar automáticamente al hacer login
                DB::table('Usuario')
                    ->where('id_usuario', $user->id_usuario)
                    ->update(['contrasena' => Hash::make($password)]);
            }
        } else {
            // Contraseña ya encriptada — usar bcrypt
            $passwordValida = Hash::check($password, $user->contrasena);
        }

        if (!$passwordValida)
            return response()->json(['ok' => false, 'msg' => 'Usuario o contraseña incorrectos'], 401);

        $token = Str::random(60);
        DB::table('Usuario')->where('id_usuario', $user->id_usuario)->update(['api_token' => $token]);

        $userData = (array) $user;
        unset($userData['contrasena']);
        $userData['api_token'] = $token;
        $userData['perms'] = self::permisosParaRol($userData['rol'] ?? 'Vendedor');

        return response()->json(['ok' => true, 'data' => $userData]);
    }

    /**
     * Módulos que puede usar cada rol. Es la única fuente de verdad de permisos:
     * la usa tanto el login (para armar el menú del frontend) como el middleware
     * VerificarPermiso (para bloquear el acceso real a la API).
     */
    public static function permisosParaRol($rol)
    {
        $mapa = [
            'Administrador' => [
                'ventas', 'cotizaciones', 'alquileres', 'inventario', 'compras',
                'devoluciones', 'clientes', 'proveedores', 'usuarios', 'reportes',
                'configuracion', 'caja', 'mapa',
            ],
            'Vendedor' => ['ventas', 'cotizaciones', 'clientes', 'alquileres', 'mapa'],
            'Cajero'   => ['caja', 'ventas', 'devoluciones'],
            'Bodega'   => ['inventario', 'compras', 'proveedores', 'alquileres'],
        ];

        return $mapa[$rol] ?? $mapa['Vendedor'];
    }

    public function cambiarPassword(Request $request)
    {
        // El usuario solo puede cambiar SU PROPIA contraseña: se toma del token,
        // nunca del id que mande el cliente (evita que cualquiera cambie la de otro).
        $authUser = $request->attributes->get('_authUser');
        $uid      = $authUser->id_usuario ?? null;
        $actual   = $request->input('actual');
        $nueva    = $request->input('nueva');

        if (!$uid || !$actual || !$nueva)
            return response()->json(['ok' => false, 'msg' => 'Datos incompletos'], 400);

        if (strlen($nueva) < 4)
            return response()->json(['ok' => false, 'msg' => 'Mínimo 4 caracteres'], 400);

        $user = DB::table('Usuario')->where('id_usuario', $uid)->first();
        if (!$user)
            return response()->json(['ok' => false, 'msg' => 'Usuario no encontrado'], 404);

        // Verificar contraseña actual (soporta texto plano y bcrypt)
        $valida = Hash::needsRehash($user->contrasena)
            ? $user->contrasena === $actual
            : Hash::check($actual, $user->contrasena);

        if (!$valida)
            return response()->json(['ok' => false, 'msg' => 'Contraseña actual incorrecta'], 401);

        DB::table('Usuario')
            ->where('id_usuario', $uid)
            ->update(['contrasena' => Hash::make($nueva)]);

        return response()->json(['ok' => true, 'msg' => 'Contraseña actualizada']);
    }
}