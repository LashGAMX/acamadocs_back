<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departamentos;
use App\Models\DetalleUsuarios;
use App\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Autenticación vía API para AcamaDocs.
     * Soporta login con 'correo' o 'user' y 'contrasena' o 'password'.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'nullable|string',
            'user' => 'nullable|string',
            'contrasena' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $username = $request->input('correo') ?? $request->input('user');
        $password = $request->input('contrasena') ?? $request->input('password');

        if (empty($username) || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe ingresar usuario/correo y contraseña',
            ], 422);
        }

        $usuario = Usuarios::where('User', $username)->first();

        if (! $usuario || ! Hash::check($password, $usuario->Pass)) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales dadas no son correctas',
            ], 401);
        }

        $detalle = DetalleUsuarios::where('Id_usuario', $usuario->Id_user)->first();
        $departamento = $detalle ? Departamentos::find($detalle->Id_departamento) : null;

        $isAdmin = ($usuario->Rol === 'Administrador') || ($departamento && $departamento->Id_departamento == 1);

        // Generar token Sanctum
        $token = $usuario->createToken('acamadocs-api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Autenticación exitosa',
            'token' => $token,
            'user' => [
                'id' => $usuario->Id_user,
                'username' => $usuario->User,
                'rol' => $isAdmin ? 'Administrador' : ($usuario->Rol ?? 'Operativo'),
                'is_admin' => $isAdmin,
                'nombre' => $detalle ? $detalle->Nombre : $usuario->User,
                'id_departamento' => $departamento ? $departamento->Id_departamento : null,
                'departamento' => $departamento ? $departamento->Departamento : null,
                'foto_perfil' => $detalle ? $detalle->Foto_perfil : null,
                'firma' => $detalle ? $detalle->Firma : null,
            ],
        ]);
    }

    /**
     * Obtener datos del usuario autenticado actual.
     */
    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if (! $usuario) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        $detalle = DetalleUsuarios::where('Id_usuario', $usuario->Id_user)->first();
        $departamento = $detalle ? Departamentos::find($detalle->Id_departamento) : null;
        $isAdmin = ($usuario->Rol === 'Administrador') || ($departamento && $departamento->Id_departamento == 1);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $usuario->Id_user,
                'username' => $usuario->User,
                'rol' => $isAdmin ? 'Administrador' : ($usuario->Rol ?? 'Operativo'),
                'is_admin' => $isAdmin,
                'nombre' => $detalle ? $detalle->Nombre : $usuario->User,
                'id_departamento' => $departamento ? $departamento->Id_departamento : null,
                'departamento' => $departamento ? $departamento->Departamento : null,
                'foto_perfil' => $detalle ? $detalle->Foto_perfil : null,
                'firma' => $detalle ? $detalle->Firma : null,
            ],
        ]);
    }

    /**
     * Cierre de sesión y revocación del token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
        ]);
    }
}
