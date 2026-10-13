<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departamentos;
use App\Models\Documentos;
use App\Models\DocumentosDepartamentos;
use App\Models\DocumentosUsuarios;
use App\Models\MenusUsuarios;
use App\Models\Procedimientos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsignacionController extends Controller
{
    /**
     * Obtener documentos asignados y no asignados a un usuario específico.
     */
    public function getDocumentosAsignados(Request $request): JsonResponse
    {
        $idUsuario = $request->input('id_usuario');

        if (! $idUsuario) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetro id_usuario requerido',
            ], 422);
        }

        $idsAsignados = DB::table('documentos_usuarios')
            ->where('Id_usuario', $idUsuario)
            ->whereNull('deleted_at')
            ->pluck('Id_documento');

        $noAsignados = DB::table('documentos')
            ->join('procedimientos', 'procedimientos.Id_procedimiento', '=', 'documentos.Id_procedimiento')
            ->whereNotIn('documentos.Id_documento', $idsAsignados)
            ->whereNull('documentos.deleted_at')
            ->select(
                'documentos.Id_documento',
                'procedimientos.Clave_procedimiento',
                'documentos.Clave_documento',
                'documentos.Documento',
                'documentos.Descripcion'
            )
            ->orderBy('documentos.Id_documento', 'desc')
            ->get();

        $asignados = DB::table('documentos')
            ->join('procedimientos', 'procedimientos.Id_procedimiento', '=', 'documentos.Id_procedimiento')
            ->join('documentos_usuarios', function ($join) use ($idUsuario) {
                $join->on('documentos_usuarios.Id_documento', '=', 'documentos.Id_documento')
                    ->where('documentos_usuarios.Id_usuario', '=', $idUsuario)
                    ->whereNull('documentos_usuarios.deleted_at');
            })
            ->whereIn('documentos.Id_documento', $idsAsignados)
            ->whereNull('documentos.deleted_at')
            ->select(
                'documentos.Id_documento',
                'procedimientos.Clave_procedimiento',
                'documentos.Clave_documento',
                'documentos.Documento',
                'documentos.Descripcion',
                'documentos_usuarios.Estado_revision',
                'documentos_usuarios.Estado_lectura'
            )
            ->orderBy('documentos.Id_documento', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'noAsignados' => $noAsignados,
            'asignados' => $asignados,
        ]);
    }

    /**
     * Asignar documento individualmente a un usuario.
     */
    public function setDocumentoIndividual(Request $request): JsonResponse
    {
        $idDocumento = $request->input('id_documento');
        $idUsuario = $request->input('id_usuario');
        $authId = $request->user()?->Id_user ?? 1;

        $registro = DocumentosUsuarios::updateOrCreate(
            [
                'Id_documento' => $idDocumento,
                'Id_usuario' => $idUsuario,
            ],
            [
                'Estado_revision' => 0,
                'Estado_lectura' => 0,
                'Id_user_c' => $authId,
                'Id_user_m' => $authId,
                'deleted_at' => null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Documento asignado exitosamente',
            'model' => $registro,
        ]);
    }

    /**
     * Quitar asignación individual de un documento.
     */
    public function delDocumentoIndividual(Request $request): JsonResponse
    {
        $idDocumento = $request->input('id_documento');
        $idUsuario = $request->input('id_usuario');

        DocumentosUsuarios::where('Id_documento', $idDocumento)
            ->where('Id_usuario', $idUsuario)
            ->delete();

        return response()->json([
            'success' => true,
            'Mensaje' => 'Registro borrado con éxito',
        ]);
    }

    /**
     * Obtener menús y accesos asignados a un usuario.
     */
    public function getMenusAsignados(Request $request): JsonResponse
    {
        $idUsuario = $request->input('id_usuario');

        $menus = MenusUsuarios::where('Id_usuario', $idUsuario)->get();
        $departamentos = Departamentos::all();
        $procedimientos = Procedimientos::all();

        return response()->json([
            'success' => true,
            'modelMenusUsuarios' => $menus,
            'modelDepartamentos' => $departamentos,
            'modelProcedimientos' => $procedimientos,
        ]);
    }

    /**
     * Asignar masivamente documentos por Departamento o Procedimiento.
     */
    public function setDocumentosMenu(Request $request): JsonResponse
    {
        $tipo = $request->input('tipo'); // 'Departamento' o 'Procedimiento'
        $idMenu = $request->input('id_menu');
        $idUsuario = $request->input('id_usuario');
        $authId = $request->user()?->Id_user ?? 1;

        if ($tipo === 'Departamento') {
            $existente = MenusUsuarios::where('Id_departamento', $idMenu)
                ->where('Id_usuario', $idUsuario)
                ->first();

            if ($existente) {
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => 'Este menú ya está asignado',
                ]);
            }

            MenusUsuarios::create([
                'Id_usuario' => $idUsuario,
                'Id_departamento' => $idMenu,
                'Id_user_c' => $authId,
                'Id_user_m' => $authId,
            ]);

            $idYaAsignados = DocumentosUsuarios::where('Id_usuario', $idUsuario)->pluck('Id_documento');
            $docsDepto = DocumentosDepartamentos::where('Id_departamento', $idMenu)
                ->whereNotIn('Id_documento', $idYaAsignados)
                ->get();

            $asignados = [];
            foreach ($docsDepto as $doc) {
                $asignados[] = DocumentosUsuarios::create([
                    'Id_documento' => $doc->Id_documento,
                    'Id_usuario' => $idUsuario,
                    'Estado_revision' => 1,
                    'Id_user_c' => $authId,
                    'Id_user_m' => $authId,
                ]);
            }

            return response()->json([
                'estado' => 'exito',
                'mensaje' => 'Menú de departamento asignado con éxito',
                'model' => $asignados,
            ]);
        } elseif ($tipo === 'Procedimiento') {
            $existente = MenusUsuarios::where('Id_procedimiento', $idMenu)
                ->where('Id_usuario', $idUsuario)
                ->first();

            if ($existente) {
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => 'Este menú ya está asignado',
                ]);
            }

            MenusUsuarios::create([
                'Id_usuario' => $idUsuario,
                'Id_procedimiento' => $idMenu,
                'Id_user_c' => $authId,
                'Id_user_m' => $authId,
            ]);

            $idYaAsignados = DocumentosUsuarios::where('Id_usuario', $idUsuario)->pluck('Id_documento');
            $docsProc = Documentos::where('Id_procedimiento', $idMenu)
                ->whereNotIn('Id_documento', $idYaAsignados)
                ->get();

            $asignados = [];
            foreach ($docsProc as $doc) {
                $asignados[] = DocumentosUsuarios::create([
                    'Id_documento' => $doc->Id_documento,
                    'Id_usuario' => $idUsuario,
                    'Estado_revision' => 1,
                    'Id_user_c' => $authId,
                    'Id_user_m' => $authId,
                ]);
            }

            return response()->json([
                'estado' => 'exito',
                'mensaje' => 'Menú de procedimiento asignado con éxito',
                'model' => $asignados,
            ]);
        }

        return response()->json(['estado' => 'error', 'mensaje' => 'Tipo no válido'], 400);
    }

    /**
     * Desasignar menú por Departamento o Procedimiento.
     */
    public function delDocumentosMenu(Request $request): JsonResponse
    {
        $idUsuario = $request->input('id_usuario');
        $idMenu = $request->input('id_menu');
        $tipo = $request->input('tipo');

        $menu = MenusUsuarios::where('Id_usuario', $idUsuario)
            ->when($tipo === 'Departamento', fn ($q) => $q->where('Id_departamento', $idMenu))
            ->when($tipo === 'Procedimiento', fn ($q) => $q->where('Id_procedimiento', $idMenu))
            ->first();

        if (! $menu) {
            return response()->json([
                'estado' => 'error',
                'mensaje' => 'Ese menú no está asignado a este usuario',
            ]);
        }

        if ($tipo === 'Departamento') {
            $ids = DocumentosDepartamentos::where('Id_departamento', $idMenu)->pluck('Id_documento');
        } else {
            $ids = Documentos::where('Id_procedimiento', $idMenu)->pluck('Id_documento');
        }

        DocumentosUsuarios::where('Id_usuario', $idUsuario)->whereIn('Id_documento', $ids)->delete();
        $menu->delete();

        return response()->json([
            'estado' => 'exito',
            'mensaje' => 'Registro eliminado',
        ]);
    }

    /**
     * Alternar estado de revisión de documentos.
     */
    public function upRevision(Request $request): JsonResponse
    {
        $tipo = $request->input('tipo');
        $idUsuario = $request->input('id_usuario');

        if ($tipo === 'Individual') {
            $idDocumento = $request->input('id_documento');
            $doc = DocumentosUsuarios::where('Id_usuario', $idUsuario)
                ->where('Id_documento', $idDocumento)
                ->first();

            if ($doc) {
                if ($doc->Estado_revision == 0) {
                    $doc->Estado_revision = 1;
                } else {
                    $doc->Estado_revision = 0;
                    $doc->Estado_lectura = 1;
                }
                $doc->save();
            }
        } elseif ($tipo === 'Conjunto') {
            $accion = $request->input('accion');
            $docs = DocumentosUsuarios::where('Id_usuario', $idUsuario)->get();

            foreach ($docs as $doc) {
                if ($accion === 'Poner') {
                    $doc->Estado_revision = 1;
                } elseif ($accion === 'Quitar') {
                    $doc->Estado_revision = 0;
                    $doc->Estado_lectura = 1;
                }
                $doc->save();
            }
        }

        return response()->json([
            'estado' => 'exito',
            'mensaje' => 'Asignación de revisión exitosa',
        ]);
    }

    /**
     * Poner o quitar revisión para todos los usuarios.
     */
    public function upRevisionTodos(): JsonResponse
    {
        DocumentosUsuarios::query()->update([
            'Estado_revision' => 0,
            'Estado_lectura' => 1,
        ]);

        return response()->json([
            'estado' => 'exito',
            'mensaje' => 'Todos los documentos marcados como revisados',
        ]);
    }

    /**
     * Confirmación de revisión efectuada por el usuario final desde el visor.
     */
    public function upRevisionUsuario(Request $request): JsonResponse
    {
        $idDocumento = $request->input('id_documento');
        $idUsuario = $request->input('id_usuario') ?? $request->user()?->Id_user;

        DocumentosUsuarios::where('Id_documento', $idDocumento)
            ->where('Id_usuario', $idUsuario)
            ->update([
                'Estado_revision' => 0,
                'Estado_lectura' => 1,
            ]);

        return response()->json([
            'estado' => 'exito',
            'mensaje' => 'Documento revisado y confirmado',
        ]);
    }

    /**
     * Comprobar estado uniforme de revisiones para un usuario.
     */
    public function getEstadoRevisiones(Request $request): JsonResponse
    {
        $idUsuario = $request->input('id_usuario');
        $estados = DocumentosUsuarios::where('Id_usuario', $idUsuario)->pluck('Estado_revision');

        $unicos = array_unique($estados->toArray(), SORT_REGULAR);
        if (count($unicos) === 1) {
            $forma = $estados->first() == 1 ? 'activa' : 'inactiva';

            return response()->json(['estado' => 'uniforme', 'forma' => $forma]);
        }

        return response()->json(['estado' => 'diferente', 'forma' => 'ninguna']);
    }

    /**
     * Comprobar estado uniforme global de revisiones.
     */
    public function getEstadoRevisionesTodos(): JsonResponse
    {
        $estados = DocumentosUsuarios::pluck('Estado_revision');
        $unicos = array_unique($estados->toArray(), SORT_REGULAR);

        if (count($unicos) === 1 && $estados->first() == 1) {
            return response()->json(['estado' => 'uniforme']);
        }

        return response()->json(['estado' => 'diferente']);
    }
}
