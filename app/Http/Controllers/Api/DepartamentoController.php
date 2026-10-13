<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departamentos;
use App\Models\DetalleUsuarios;
use App\Models\DocumentosDepartamentos;
use App\Models\MenusUsuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartamentoController extends Controller
{
    /**
     * Listar todos los departamentos.
     */
    public function index(): JsonResponse
    {
        $departamentos = Departamentos::orderBy('Departamento', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $departamentos,
            'model' => $departamentos, // compatibilidad con llamadas legadas
        ]);
    }

    /**
     * Crear un nuevo departamento.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'departamento' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->user()?->Id_user;

        $departamento = Departamentos::create([
            'Departamento' => $request->input('departamento'),
            'Descripcion' => $request->input('descripcion'),
            'Id_user_c' => $userId,
            'Id_user_m' => $userId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Departamento creado exitosamente',
            'data' => $departamento,
            'model' => $departamento,
        ], 201);
    }

    /**
     * Actualizar departamento.
     */
    public function update(Request $request, $id = null): JsonResponse
    {
        $idDepto = $id ?? $request->input('id_departamento');
        $departamento = Departamentos::find($idDepto);

        if (! $departamento) {
            return response()->json([
                'success' => false,
                'message' => 'Departamento no encontrado',
            ], 404);
        }

        $departamento->Departamento = $request->input('departamento', $departamento->Departamento);
        $departamento->Descripcion = $request->input('descripcion', $departamento->Descripcion);
        $departamento->Id_user_m = $request->user()?->Id_user;
        $departamento->save();

        return response()->json([
            'success' => true,
            'message' => 'Departamento actualizado exitosamente',
            'data' => $departamento,
            'model' => $departamento,
        ]);
    }

    /**
     * Eliminar departamento con reasignación segura.
     */
    public function destroy(Request $request, $id = null): JsonResponse
    {
        $idDepto = $id ?? $request->input('id_departamento');
        $departamento = Departamentos::find($idDepto);

        if (! $departamento) {
            return response()->json([
                'success' => false,
                'message' => 'Departamento no encontrado',
            ], 404);
        }

        // Reasignar usuarios al departamento por defecto
        $sinDepto = Departamentos::where('Departamento', 'Sin Departamento')->first();
        $fallbackId = $sinDepto ? $sinDepto->Id_departamento : 1;

        DetalleUsuarios::where('Id_departamento', $departamento->Id_departamento)
            ->update(['Id_departamento' => $fallbackId]);

        DocumentosDepartamentos::where('Id_departamento', $departamento->Id_departamento)->delete();
        MenusUsuarios::where('Id_departamento', $departamento->Id_departamento)->delete();

        $departamento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Departamento eliminado correctamente',
            'Respuesta' => 'Registro eliminado',
        ]);
    }
}
