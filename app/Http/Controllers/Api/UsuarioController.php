<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetalleUsuarios;
use App\Models\DocumentosUsuarios;
use App\Models\PersonalDocumentos;
use App\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    /**
     * Listar usuarios con su detalle y departamento.
     */
    public function index(Request $request): JsonResponse
    {
        $id = $request->input('id_usuario') ?? $request->input('id');
        $nombre = $request->input('nombre_usuario') ?? $request->input('nombre');
        $departamento = $request->input('departamento_usuario') ?? $request->input('id_departamento');

        $query = Usuarios::with(['detalle.departamento'])
            ->when(! empty($id), function ($q) use ($id) {
                $q->where('Id_user', $id);
            })
            ->when(! empty($nombre), function ($q) use ($nombre) {
                $q->whereHas('detalle', function ($sub) use ($nombre) {
                    $sub->where('Nombre', 'LIKE', '%'.$nombre.'%');
                });
            })
            ->when(! empty($departamento), function ($q) use ($departamento) {
                $q->whereHas('detalle', function ($sub) use ($departamento) {
                    $sub->where('Id_departamento', $departamento);
                });
            })
            ->orderBy('Id_user', 'desc');

        $usuarios = $query->get()->map(function ($u) {
            return [
                'Id_user' => $u->Id_user,
                'User' => $u->User,
                'Rol' => $u->Rol,
                'Nombre' => $u->detalle?->Nombre ?? $u->User,
                'Id_departamento' => $u->detalle?->Id_departamento,
                'Departamento' => $u->detalle?->departamento?->Departamento ?? 'Sin Departamento',
                'Firma' => $u->detalle?->Firma ? url('storage/'.str_replace('public/', '', $u->detalle->Firma)) : null,
                'Foto_perfil' => $u->detalle?->Foto_perfil ? url('storage/'.str_replace('public/', '', $u->detalle->Foto_perfil)) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $usuarios,
            'model' => $usuarios, // compatibilidad
        ]);
    }

    /**
     * Obtener detalle de un usuario específico.
     */
    public function show($id): JsonResponse
    {
        $usuario = Usuarios::with(['detalle.departamento'])->find($id);

        if (! $usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $data = [
            'Id_user' => $usuario->Id_user,
            'User' => $usuario->User,
            'Rol' => $usuario->Rol,
            'Nombre' => $usuario->detalle?->Nombre ?? '',
            'Id_departamento' => $usuario->detalle?->Id_departamento,
            'Departamento' => $usuario->detalle?->departamento?->Departamento ?? '',
            'Firma' => $usuario->detalle?->Firma ? url('storage/'.str_replace('public/', '', $usuario->detalle->Firma)) : null,
            'Foto_perfil' => $usuario->detalle?->Foto_perfil ? url('storage/'.str_replace('public/', '', $usuario->detalle->Foto_perfil)) : null,
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Crear un nuevo usuario con firma y foto.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|string|max:255|unique:usuarios,User',
            'contrasena' => 'required|string|min:4',
            'nombre' => 'required|string|max:255',
            'departamento' => 'required|integer',
            'rol' => 'nullable|string',
            'firma' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
            'foto' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $adminId = $request->user()?->Id_user;

        $usuario = Usuarios::create([
            'User' => $request->input('correo'),
            'Pass' => Hash::make($request->input('contrasena')),
            'Rol' => $request->input('rol', 'Operativo'),
            'Id_user_c' => $adminId,
            'Id_user_m' => $adminId,
        ]);

        $detalle = DetalleUsuarios::create([
            'Id_usuario' => $usuario->Id_user,
            'Id_departamento' => $request->input('departamento'),
            'Nombre' => $request->input('nombre'),
            'Id_user_c' => $adminId,
            'Id_user_m' => $adminId,
        ]);

        // Guardar Firma
        if ($request->hasFile('firma')) {
            $path = $request->file('firma')->storeAs('public/firmas', $usuario->Id_user.'.png');
            $detalle->Firma = $path;
            $detalle->save();
        }

        // Guardar Foto de perfil
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->storeAs('public/fperfil', $usuario->Id_user.'.png');
            $detalle->Foto_perfil = $path;
            $detalle->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => [
                'usuario' => $usuario,
                'detalle' => $detalle,
            ],
            'modelUsuarios' => $usuario,
            'modelDetalleUsuarios' => $detalle,
        ], 201);
    }

    /**
     * Actualizar usuario.
     */
    public function update(Request $request, $id = null): JsonResponse
    {
        $idUser = $id ?? $request->input('id');
        $usuario = Usuarios::find($idUser);

        if (! $usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        if ($request->filled('correo')) {
            $usuario->User = $request->input('correo');
        }

        if ($request->filled('contrasena')) {
            $usuario->Pass = Hash::make($request->input('contrasena'));
        }

        if ($request->filled('rol')) {
            $usuario->Rol = $request->input('rol');
        }

        $usuario->Id_user_m = $request->user()?->Id_user;
        $usuario->save();

        $detalle = DetalleUsuarios::firstOrCreate(['Id_usuario' => $usuario->Id_user]);
        if ($request->filled('departamento')) {
            $detalle->Id_departamento = $request->input('departamento');
        }
        if ($request->filled('nombre')) {
            $detalle->Nombre = $request->input('nombre');
        }

        // Actualizar firma
        if ($request->hasFile('firma')) {
            $path = $request->file('firma')->storeAs('public/firmas', $usuario->Id_user.'.png');
            $detalle->Firma = $path;
        }

        // Actualizar foto
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->storeAs('public/fperfil', $usuario->Id_user.'.png');
            $detalle->Foto_perfil = $path;
        }

        $detalle->Id_user_m = $request->user()?->Id_user;
        $detalle->save();

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => [
                'usuario' => $usuario,
                'detalle' => $detalle,
            ],
            'modelUsuarios' => $usuario,
            'modelDetalleUsuarios' => $detalle,
        ]);
    }

    /**
     * Desactivar / Eliminar usuario con reasignación en documentos.
     */
    public function destroy(Request $request, $id = null): JsonResponse
    {
        $idUser = $id ?? $request->input('id');
        $usuario = Usuarios::find($idUser);

        if (! $usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // Reasignar firmas en personal_documentos a usuario por defecto (1)
        PersonalDocumentos::where('Id_elabora', $usuario->Id_user)->update(['Id_elabora' => 1]);
        PersonalDocumentos::where('Id_autoriza', $usuario->Id_user)->update(['Id_autoriza' => 1]);

        DocumentosUsuarios::where('Id_usuario', $usuario->Id_user)->delete();
        DetalleUsuarios::where('Id_usuario', $usuario->Id_user)->delete();
        $usuario->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado correctamente',
            'model' => 'Registro eliminado',
        ]);
    }
}
