<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departamentos;
use App\Models\DetalleDocumentos;
use App\Models\Documentos;
use App\Models\DocumentosDepartamentos;
use App\Models\DocumentosUsuarios;
use App\Models\MenusUsuarios;
use App\Models\PersonalDocumentos;
use App\Models\Procedimientos;
use App\Models\TipoDocumentos;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoController extends Controller
{
    /**
     * Listado general de documentos con filtros de búsqueda y estados de caducidad.
     */
    public function index(Request $request): JsonResponse
    {
        $idUsuario = $request->input('id_usuario');
        $codigoProcedimiento = $request->input('codigo') ?? $request->input('id_procedimiento');
        $clave = $request->input('clave');
        $titulo = $request->input('titulo');
        $estado = $request->input('estado'); // '1' = alerta 47-48 meses, '2' = caducado > 48 meses

        $query = DB::table('documentos')
            ->join('procedimientos', 'procedimientos.Id_procedimiento', '=', 'documentos.Id_procedimiento')
            ->join('personal_documentos', 'personal_documentos.Id_documento', '=', 'documentos.Id_documento')
            ->join('usuarios as u_elabora', 'u_elabora.Id_user', '=', 'personal_documentos.Id_elabora')
            ->join('usuarios as u_autoriza', 'u_autoriza.Id_user', '=', 'personal_documentos.Id_autoriza')
            ->join('detalle_usuarios as d_elabora', 'd_elabora.Id_usuario', '=', 'u_elabora.Id_user')
            ->join('detalle_usuarios as d_autoriza', 'd_autoriza.Id_usuario', '=', 'u_autoriza.Id_user')
            ->leftJoin('detalle_documentos', 'detalle_documentos.Id_documento', '=', 'documentos.Id_documento')
            ->select(
                'documentos.Id_documento',
                'procedimientos.Id_procedimiento',
                'procedimientos.Clave_procedimiento',
                'documentos.Clave_documento',
                'documentos.Documento',
                'documentos.Descripcion',
                'documentos.Valido_desde',
                'documentos.Ultima_modificacion',
                'documentos.Id_tipo',
                'detalle_documentos.Numero_revision',
                'detalle_documentos.Url',
                'd_elabora.Nombre as Usuario_elabora',
                'd_autoriza.Nombre as Usuario_autoriza'
            )
            ->whereNull('documentos.deleted_at');

        if (! empty($codigoProcedimiento) && $codigoProcedimiento != '0') {
            $query->where('procedimientos.Id_procedimiento', '=', $codigoProcedimiento);
        }

        if (! empty($clave)) {
            $query->where('documentos.Clave_documento', 'LIKE', '%'.$clave.'%');
        }

        if (! empty($titulo)) {
            $query->where('documentos.Documento', 'LIKE', '%'.$titulo.'%');
        }

        if (! empty($idUsuario)) {
            $docsPermitidos = DocumentosUsuarios::where('Id_usuario', $idUsuario)->pluck('Id_documento');
            $query->whereIn('documentos.Id_documento', $docsPermitidos);
        }

        // Reglas de caducidad
        if ($estado == '1') {
            $query->whereIn('procedimientos.Clave_procedimiento', ['MC', 'PG', 'PE'])
                ->where('documentos.Ultima_modificacion', '<=', Carbon::now('UTC')->subMonths(47)->toDateTimeString())
                ->where('documentos.Ultima_modificacion', '>', Carbon::now('UTC')->subMonths(48)->toDateTimeString());
        } elseif ($estado == '2') {
            $query->whereIn('procedimientos.Clave_procedimiento', ['MC', 'PG', 'PE'])
                ->where('documentos.Ultima_modificacion', '<=', Carbon::now('UTC')->subMonths(48)->toDateTimeString());
        }

        $documentos = $query->orderBy('documentos.Id_documento', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $documentos,
            'model' => $documentos,
        ]);
    }

    /**
     * Mis documentos asignados (vista operativa para usuarios no-administradores).
     */
    public function misDocumentos(Request $request): JsonResponse
    {
        $idUsuario = $request->user()?->Id_user ?? $request->input('id_usuario');
        $codigo = $request->input('codigo');
        $clave = $request->input('clave');
        $titulo = $request->input('titulo');

        $idsAsignados = DocumentosUsuarios::where('Id_usuario', $idUsuario)->pluck('Id_documento');

        $query = DB::table('documentos')
            ->join('procedimientos', 'procedimientos.Id_procedimiento', '=', 'documentos.Id_procedimiento')
            ->join('personal_documentos', 'personal_documentos.Id_documento', '=', 'documentos.Id_documento')
            ->join('usuarios as u_elabora', 'u_elabora.Id_user', '=', 'personal_documentos.Id_elabora')
            ->join('usuarios as u_autoriza', 'u_autoriza.Id_user', '=', 'personal_documentos.Id_autoriza')
            ->join('detalle_usuarios as d_elabora', 'd_elabora.Id_usuario', '=', 'u_elabora.Id_user')
            ->join('detalle_usuarios as d_autoriza', 'd_autoriza.Id_usuario', '=', 'u_autoriza.Id_user')
            ->join('documentos_usuarios', function ($join) use ($idUsuario) {
                $join->on('documentos_usuarios.Id_documento', '=', 'documentos.Id_documento')
                    ->where('documentos_usuarios.Id_usuario', '=', $idUsuario)
                    ->whereNull('documentos_usuarios.deleted_at');
            })
            ->select(
                'procedimientos.Clave_procedimiento',
                'documentos.Id_documento',
                'documentos.Clave_documento',
                'documentos.Documento',
                'documentos.Descripcion',
                'documentos.Valido_desde',
                'documentos.Ultima_modificacion',
                'documentos.Id_tipo',
                'd_elabora.Nombre as Usuario_elabora',
                'd_autoriza.Nombre as Usuario_autoriza',
                'documentos_usuarios.Estado_revision',
                'documentos_usuarios.Estado_lectura'
            )
            ->whereIn('documentos.Id_documento', $idsAsignados)
            ->whereNull('documentos.deleted_at');

        if (! empty($codigo) && $codigo != '0') {
            $query->where('procedimientos.Id_procedimiento', '=', $codigo);
        }

        if (! empty($clave)) {
            $query->where('documentos.Clave_documento', 'LIKE', '%'.$clave.'%');
        }

        if (! empty($titulo)) {
            $query->where('documentos.Documento', 'LIKE', '%'.$titulo.'%');
        }

        $documentos = $query->orderBy('documentos_usuarios.Estado_revision', 'desc')
            ->orderBy('documentos.Id_documento', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documentos,
            'model' => $documentos,
        ]);
    }

    /**
     * Obtener detalle completo de un documento para edición o consulta.
     */
    public function show(Request $request, $id = null): JsonResponse
    {
        $idDocumento = $id ?? $request->input('id_documento');

        $doc = Documentos::find($idDocumento);
        if (! $doc) {
            return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 404);
        }

        $detalle = DetalleDocumentos::where('Id_documento', $doc->Id_documento)->first();
        $personal = PersonalDocumentos::where('Id_documento', $doc->Id_documento)->first();
        $procedimiento = Procedimientos::find($doc->Id_procedimiento);
        $tipo = TipoDocumentos::find($doc->Id_tipo);
        $deptos = DocumentosDepartamentos::where('Id_documento', $doc->Id_documento)->pluck('Id_departamento');

        $data = [
            'Id_documento' => $doc->Id_documento,
            'Id_procedimiento' => $doc->Id_procedimiento,
            'Clave_procedimiento' => $procedimiento?->Clave_procedimiento,
            'Clave_documento' => $doc->Clave_documento,
            'Documento' => $doc->Documento,
            'Descripcion' => $doc->Descripcion,
            'Id_tipo' => $doc->Id_tipo,
            'Tipo' => $tipo?->Tipo,
            'Numero_revision' => $detalle?->Numero_revision ?? '00',
            'Valido_desde' => $doc->Valido_desde,
            'Ultima_modificacion' => $doc->Ultima_modificacion,
            'Id_elabora' => $personal?->Id_elabora,
            'Id_autoriza' => $personal?->Id_autoriza,
            'Contenido' => $detalle?->Contenido,
            'Url' => $detalle?->Url,
            'Departamentos' => $deptos,
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'model' => [$data], // compatibilidad con array
            'listaDepartamentos' => DocumentosDepartamentos::where('Id_documento', $doc->Id_documento)->get(),
            'tipoDocumento' => $tipo,
        ]);
    }

    /**
     * Crear un nuevo documento y asignar a menús/departamentos.
     */
    public function store(Request $request): JsonResponse
    {
        $claveProcedimiento = $request->input('clave_procedimiento');
        $claveDocumento = $request->input('clave_documento');

        // Validar unicidad
        $existe = DB::table('documentos')
            ->where('Clave_documento', $claveDocumento)
            ->where('Id_procedimiento', $claveProcedimiento)
            ->whereNull('deleted_at')
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un documento con esa clave y procedimiento',
                'mensaje' => 'error',
            ], 422);
        }

        $userId = $request->user()?->Id_user ?? 1;

        $doc = Documentos::create([
            'Id_procedimiento' => $claveProcedimiento,
            'Id_tipo' => $request->input('id_tipo'),
            'Documento' => $request->input('documento'),
            'Descripcion' => $request->input('descripcion'),
            'Clave_documento' => $claveDocumento,
            'Valido_desde' => $request->input('valido_desde'),
            'Ultima_modificacion' => $request->input('ultima_modificacion'),
            'Id_user_c' => $userId,
            'Id_user_m' => $userId,
        ]);

        PersonalDocumentos::create([
            'Id_documento' => $doc->Id_documento,
            'Id_elabora' => $request->input('id_elabora'),
            'Id_autoriza' => $request->input('id_autoriza'),
            'Id_user_c' => $userId,
            'Id_user_m' => $userId,
        ]);

        $procedimiento = Procedimientos::find($claveProcedimiento);
        $codigoProc = $procedimiento ? $procedimiento->Clave_procedimiento : 'DOC';

        $idTipo = (int) $request->input('id_tipo');
        $urlArchivo = null;
        $contenido = null;

        if ($idTipo === 1 && $request->hasFile('archivo')) {
            $nombreArchivo = $codigoProc.'-'.$claveDocumento.'.pdf';
            $urlArchivo = $request->file('archivo')->storeAs('public/pdf', $nombreArchivo);
        } elseif ($idTipo === 2) {
            $contenido = $request->input('contenido_url');
            if ($contenido) {
                $temp = explode('<p data-f-id="pbf"', $contenido);
                $contenido = $temp[0];
            }
        } elseif ($idTipo === 3 && $request->hasFile('archivo')) {
            $nombreArchivo = $codigoProc.'-'.$claveDocumento.'.docx';
            $urlArchivo = $request->file('archivo')->storeAs('public/word', $nombreArchivo);
        }

        $detalle = DetalleDocumentos::create([
            'Id_documento' => $doc->Id_documento,
            'Numero_revision' => $request->input('numero_revision', '00'),
            'Contenido' => $contenido,
            'Url' => $urlArchivo,
            'Id_user_c' => $userId,
            'Id_user_m' => $userId,
        ]);

        // Asignar al departamento 1 (Administración) siempre
        DocumentosDepartamentos::create([
            'Id_documento' => $doc->Id_documento,
            'Id_departamento' => 1,
            'Id_user_c' => $userId,
            'Id_user_m' => $userId,
        ]);

        // Asignar a los demás departamentos seleccionados
        $departamentos = $request->input('id_departamento', []);
        if (is_string($departamentos)) {
            $departamentos = json_decode($departamentos, true) ?? [];
        }

        foreach ($departamentos as $deptoId) {
            if ($deptoId != 1) {
                DocumentosDepartamentos::create([
                    'Id_documento' => $doc->Id_documento,
                    'Id_departamento' => $deptoId,
                    'Id_user_c' => $userId,
                    'Id_user_m' => $userId,
                ]);
            }
        }

        if ($idTipo === 3) {
            app(VisorController::class)->compilarWordAPdf($doc, $detalle, $codigoProc.'-'.$claveDocumento, true);
        }

        // Asignar en cascada a los usuarios con menús correspondientes
        $menus = MenusUsuarios::whereIn('Id_departamento', $departamentos)
            ->orWhere('Id_procedimiento', $claveProcedimiento)
            ->whereNull('deleted_at')
            ->select(DB::raw('MIN(Id_menu) as Id_menu'), 'Id_usuario')
            ->groupBy('Id_usuario')
            ->get();

        foreach ($menus as $m) {
            DocumentosUsuarios::create([
                'Id_documento' => $doc->Id_documento,
                'Id_usuario' => $m->Id_usuario,
                'Estado_revision' => 1,
                'Id_user_c' => $userId,
                'Id_user_m' => $userId,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Documento creado exitosamente',
            'mensaje' => 'exito',
            'data' => $doc,
        ], 201);
    }

    /**
     * Actualizar documento y propagar aviso de revisión a usuarios.
     */
    public function update(Request $request, $id = null): JsonResponse
    {
        $idDocumento = $id ?? $request->input('id_documento');
        $doc = Documentos::find($idDocumento);

        if (! $doc) {
            return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 404);
        }

        $userId = $request->user()?->Id_user ?? 1;

        $claveProcedimiento = $request->input('clave_procedimiento', $doc->Id_procedimiento);
        $claveDocumento = $request->input('clave_documento', $doc->Clave_documento);

        // Actualizar datos del documento
        $doc->Id_procedimiento = $claveProcedimiento;
        $doc->Id_tipo = $request->input('id_tipo', $doc->Id_tipo);
        $doc->Documento = $request->input('documento', $doc->Documento);
        $doc->Descripcion = $request->input('descripcion', $doc->Descripcion);
        $doc->Clave_documento = $claveDocumento;
        $doc->Valido_desde = $request->input('valido_desde', $doc->Valido_desde);
        $doc->Ultima_modificacion = $request->input('ultima_modificacion', $doc->Ultima_modificacion);
        $doc->Id_user_m = $userId;
        $doc->save();

        // Actualizar personal
        $personal = PersonalDocumentos::firstOrCreate(['Id_documento' => $doc->Id_documento]);
        if ($request->filled('id_elabora')) {
            $personal->Id_elabora = $request->input('id_elabora');
        }
        if ($request->filled('id_autoriza')) {
            $personal->Id_autoriza = $request->input('id_autoriza');
        }
        $personal->Id_user_m = $userId;
        $personal->save();

        // Actualizar detalle (archivo o contenido)
        $detalle = DetalleDocumentos::firstOrCreate(['Id_documento' => $doc->Id_documento]);
        if ($request->filled('numero_revision')) {
            $detalle->Numero_revision = $request->input('numero_revision');
        }

        $idTipo = (int) $doc->Id_tipo;
        $procedimiento = Procedimientos::find($doc->Id_procedimiento);
        $codigoProc = $procedimiento ? $procedimiento->Clave_procedimiento : 'DOC';

        if ($idTipo === 2) {
            $contenido = $request->input('contenido_url');
            if ($contenido) {
                $temp = explode('<p data-f-id="pbf"', $contenido);
                $detalle->Contenido = $temp[0];
            }
            $detalle->Url = null;
        } elseif ($request->hasFile('archivo')) {
            if ($idTipo === 1) {
                $nombreArchivo = $codigoProc.'-'.$claveDocumento.'.pdf';
                $detalle->Url = $request->file('archivo')->storeAs('public/pdf', $nombreArchivo);
            } elseif ($idTipo === 3) {
                $nombreArchivo = $codigoProc.'-'.$claveDocumento.'.docx';
                $detalle->Url = $request->file('archivo')->storeAs('public/word', $nombreArchivo);
            }
            $detalle->Contenido = null;
        }

        $detalle->Id_user_m = $userId;
        $detalle->save();

        // Actualizar departamentos asociados
        $departamentos = $request->input('id_departamento');
        if (is_string($departamentos)) {
            $departamentos = json_decode($departamentos, true) ?? [];
        }

        if (is_array($departamentos)) {
            DocumentosDepartamentos::where('Id_documento', $doc->Id_documento)
                ->where('Id_departamento', '!=', 1)
                ->delete();

            foreach ($departamentos as $deptoId) {
                if ($deptoId != 1) {
                    DocumentosDepartamentos::firstOrCreate([
                        'Id_documento' => $doc->Id_documento,
                        'Id_departamento' => $deptoId,
                    ], ['Id_user_c' => $userId, 'Id_user_m' => $userId]);
                }
            }
        }

        if ($idTipo === 3) {
            app(VisorController::class)->compilarWordAPdf($doc, $detalle, $codigoProc.'-'.$claveDocumento, true);
        }

        // Cascada de revisión: resetear Estado_revision = 1 para que los usuarios asignados confirmen la nueva lectura
        DocumentosUsuarios::where('Id_documento', $doc->Id_documento)->update([
            'Estado_revision' => 1,
            'Estado_lectura' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Documento actualizado exitosamente',
            'mensaje' => 'exito',
            'data' => $doc,
        ]);
    }

    /**
     * Eliminar documento (soft delete).
     */
    public function destroy(Request $request, $id = null): JsonResponse
    {
        $idDocumento = $id ?? $request->input('id_documento');
        $doc = Documentos::find($idDocumento);

        if (! $doc) {
            return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 404);
        }

        PersonalDocumentos::where('Id_documento', $doc->Id_documento)->delete();
        DetalleDocumentos::where('Id_documento', $doc->Id_documento)->delete();
        DocumentosDepartamentos::where('Id_documento', $doc->Id_documento)->delete();
        DocumentosUsuarios::where('Id_documento', $doc->Id_documento)->delete();
        $doc->delete();

        return response()->json([
            'success' => true,
            'message' => 'Documento eliminado correctamente',
            'Respuesta' => 'Documentos eliminado',
        ]);
    }

    /**
     * Exportación de la Lista Maestra de Documentos a formato Excel/CSV.
     */
    public function exportExcel(Request $request, $procedimientos = null)
    {
        $procFiltro = $procedimientos ?? $request->input('procedimiento');

        $query = DB::table('documentos')
            ->join('procedimientos', 'procedimientos.Id_procedimiento', '=', 'documentos.Id_procedimiento')
            ->join('personal_documentos', 'personal_documentos.Id_documento', '=', 'documentos.Id_documento')
            ->join('usuarios as u_elabora', 'u_elabora.Id_user', '=', 'personal_documentos.Id_elabora')
            ->join('usuarios as u_autoriza', 'u_autoriza.Id_user', '=', 'personal_documentos.Id_autoriza')
            ->join('detalle_usuarios as d_elabora', 'd_elabora.Id_usuario', '=', 'u_elabora.Id_user')
            ->join('detalle_usuarios as d_autoriza', 'd_autoriza.Id_usuario', '=', 'u_autoriza.Id_user')
            ->leftJoin('detalle_documentos', 'detalle_documentos.Id_documento', '=', 'documentos.Id_documento')
            ->select(
                'procedimientos.Clave_procedimiento',
                'documentos.Clave_documento',
                'documentos.Documento',
                'detalle_documentos.Numero_revision',
                'documentos.Valido_desde',
                'documentos.Ultima_modificacion',
                'd_elabora.Nombre as Elaboro',
                'd_autoriza.Nombre as Autorizo'
            )
            ->whereNull('documentos.deleted_at');

        if (! empty($procFiltro) && $procFiltro != '0') {
            $query->where('procedimientos.Id_procedimiento', $procFiltro);
        }

        $documentos = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="lista-maestra-'.date('Y-m-d').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($documentos) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($handle, ['Código Procedimiento', 'Clave Documento', 'Nombre del Documento', 'Revisión', 'Válido Desde', 'Última Modificación', 'Elaboró', 'Autorizó']);

            foreach ($documentos as $row) {
                fputcsv($handle, [
                    $row->Clave_procedimiento,
                    $row->Clave_documento,
                    $row->Documento,
                    $row->Numero_revision,
                    $row->Valido_desde,
                    $row->Ultima_modificacion,
                    $row->Elaboro,
                    $row->Autorizo,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
