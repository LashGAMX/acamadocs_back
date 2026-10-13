<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Procedimientos;
use App\Models\TipoDocumentos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProcedimientoController extends Controller
{
    /**
     * Listar todos los procedimientos.
     */
    public function index(): JsonResponse
    {
        $procedimientos = Procedimientos::orderBy('Clave_procedimiento', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $procedimientos,
            'model' => $procedimientos,
        ]);
    }

    /**
     * Crear un procedimiento.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'procedimiento' => 'required|string|max:255',
            'clave_procedimiento' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->user()?->Id_user;

        $procedimiento = Procedimientos::create([
            'Procedimiento' => $request->input('procedimiento'),
            'Clave_procedimiento' => strtoupper($request->input('clave_procedimiento')),
            'Id_user_c' => $userId,
            'Id_user_m' => $userId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Procedimiento creado exitosamente',
            'data' => $procedimiento,
            'model' => $procedimiento,
        ], 201);
    }

    /**
     * Actualizar un procedimiento.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $procedimiento = Procedimientos::find($id);

        if (! $procedimiento) {
            return response()->json([
                'success' => false,
                'message' => 'Procedimiento no encontrado',
            ], 404);
        }

        $procedimiento->Procedimiento = $request->input('procedimiento', $procedimiento->Procedimiento);
        if ($request->has('clave_procedimiento')) {
            $procedimiento->Clave_procedimiento = strtoupper($request->input('clave_procedimiento'));
        }
        $procedimiento->Id_user_m = $request->user()?->Id_user;
        $procedimiento->save();

        return response()->json([
            'success' => true,
            'message' => 'Procedimiento actualizado exitosamente',
            'data' => $procedimiento,
        ]);
    }

    /**
     * Listar todos los tipos de documento (PDF, WORD, etc.).
     */
    public function tipos(): JsonResponse
    {
        $tipos = TipoDocumentos::all();

        return response()->json([
            'success' => true,
            'data' => $tipos,
            'model' => $tipos,
        ]);
    }
}
