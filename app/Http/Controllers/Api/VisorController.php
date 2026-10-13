<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departamentos;
use App\Models\DetalleDocumentos;
use App\Models\DetalleUsuarios;
use App\Models\Documentos;
use App\Models\DocumentosDepartamentos;
use App\Models\DocumentosUsuarios;
use App\Models\PersonalDocumentos;
use App\Models\Procedimientos;
use App\Models\TipoDocumentos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class VisorController extends Controller
{
    /**
     * Obtener metadatos y URL o contenido para previsualizar un documento.
     */
    public function verDocumento(Request $request, $id): JsonResponse
    {
        $doc = Documentos::find($id);

        if (! $doc) {
            return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 404);
        }

        $procedimiento = Procedimientos::find($doc->Id_procedimiento);
        $tipo = TipoDocumentos::find($doc->Id_tipo);
        $detalle = DetalleDocumentos::where('Id_documento', $doc->Id_documento)->first();
        $personal = PersonalDocumentos::where('Id_documento', $doc->Id_documento)->first();

        $elabora = $personal ? DetalleUsuarios::where('Id_usuario', $personal->Id_elabora)->first() : null;
        $autoriza = $personal ? DetalleUsuarios::where('Id_usuario', $personal->Id_autoriza)->first() : null;

        $user = $request->user();
        $userId = $user?->Id_user;
        $userDetalle = $userId ? DetalleUsuarios::where('Id_usuario', $userId)->first() : null;
        $isAdmin = ($user?->Rol === 'Administrador') || ($userDetalle && $userDetalle->Id_departamento == 1);
        $asignacion = $userId ? DocumentosUsuarios::where('Id_documento', $doc->Id_documento)->where('Id_usuario', $userId)->first() : null;

        $claveCompleta = ($procedimiento ? $procedimiento->Clave_procedimiento : '').'-'.$doc->Clave_documento;

        $url = null;
        $tipoVisualizador = 'pdf'; // 'pdf', 'html', 'docx'

        if ($tipo && $tipo->Tipo === 'PDF' && $detalle && $detalle->Url) {
            $tipoVisualizador = 'pdf';
            $url = url('storage/'.str_replace('public/', '', $detalle->Url)).'?v='.time();
        } elseif ($tipo && $tipo->Tipo === 'WORD') {
            $compiledPdf = $this->compilarWordAPdf($doc, $detalle, $claveCompleta);
            if ($compiledPdf) {
                $tipoVisualizador = 'pdf';
                $url = $compiledPdf;
            } else {
                $tipoVisualizador = 'word';
                $url = $detalle?->Url ? url('storage/'.str_replace('public/', '', $detalle->Url)) : null;
            }
        } elseif ($tipo && $tipo->Id_tipo == 2) {
            $tipoVisualizador = 'html';
            $url = url('api/visor/'.$doc->Id_documento.'/html');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id_documento' => $doc->Id_documento,
                'clave' => $claveCompleta,
                'titulo' => $doc->Documento,
                'descripcion' => $doc->Descripcion,
                'procedimiento' => $procedimiento?->Procedimiento,
                'clave_procedimiento' => $procedimiento?->Clave_procedimiento,
                'revision' => $detalle?->Numero_revision ?? '00',
                'valido_desde' => $doc->Valido_desde,
                'ultima_modificacion' => $doc->Ultima_modificacion,
                'elaborado_por' => $elabora?->Nombre ?? 'N/A',
                'autorizado_por' => $autoriza?->Nombre ?? 'N/A',
                'tipo_visualizador' => $tipoVisualizador,
                'tipo_nombre' => $tipo?->Tipo ?? 'PDF',
                'url' => $url,
                'can_download' => $isAdmin,
                'contenido_html' => $detalle?->Contenido,
                'estado_revision' => $asignacion ? $asignacion->Estado_revision : null,
                'estado_lectura' => $asignacion ? $asignacion->Estado_lectura : null,
            ],
        ]);
    }

    /**
     * Compilar plantilla Word (.docx) a PDF usando PhpWord y LibreOffice.
     *
     * Si ya existe un PDF compilado más reciente que la plantilla y no se fuerza, se reutiliza.
     */
    public function compilarWordAPdf(Documentos $doc, ?DetalleDocumentos $detalle, string $claveCompleta, bool $forzar = false): ?string
    {
        $wordFileName = $claveCompleta.'.docx';

        $candidates = [
            storage_path('app/public/word/'.$wordFileName),
            storage_path('app/public/'.str_replace('public/', '', $detalle?->Url ?? '')),
            public_path('word/'.$wordFileName),
            storage_path('app/public/word/'.basename($detalle?->Url ?? '')),
        ];

        $docxPath = null;
        foreach ($candidates as $c) {
            if (! empty($c) && file_exists($c)) {
                $docxPath = $c;
                break;
            }
        }

        if (! $docxPath) {
            return null;
        }

        $pdfCompilado = storage_path('app/public/pdf/'.$claveCompleta.'.pdf');
        if (! $forzar && file_exists($pdfCompilado) && filemtime($pdfCompilado) >= filemtime($docxPath)) {
            return url('storage/pdf/'.$claveCompleta.'.pdf').'?v='.filemtime($pdfCompilado);
        }

        try {
            $template = new TemplateProcessor($docxPath);

            $personal = PersonalDocumentos::where('Id_documento', $doc->Id_documento)->first();
            $elabora = $personal ? DetalleUsuarios::where('Id_usuario', $personal->Id_elabora)->first() : null;
            $autoriza = $personal ? DetalleUsuarios::where('Id_usuario', $personal->Id_autoriza)->first() : null;

            $docDepto = DocumentosDepartamentos::where('Id_documento', $doc->Id_documento)->where('Id_departamento', '!=', 1)->first();
            $deptoModel = $docDepto ? Departamentos::find($docDepto->Id_departamento) : Departamentos::find(1);

            // Reemplazo de marcadores de plantilla oficial
            $template->setValue('tituloDocumento', $doc->Documento ?? '');
            $template->setValue('procedimientoNo', $claveCompleta);
            $template->setValue('validoDesde', $doc->Valido_desde ?? '');
            $template->setValue('fechaUltimaRevision', $doc->Ultima_modificacion ?? '');
            $template->setValue('revision', $detalle?->Numero_revision ?? '00');
            $template->setValue('nombreElaboradoPor', $elabora?->Nombre ?? 'N/A');
            $template->setValue('nombreAutorizadoPor', $autoriza?->Nombre ?? 'N/A');
            $template->setValue('departamento', $deptoModel?->Departamento ?? 'General');

            // Inyección de firmas gráficas
            $firmaElabora = $this->obtenerRutaFirma($personal?->Id_elabora);
            if ($firmaElabora && file_exists($firmaElabora)) {
                $template->setImageValue('firmaElaboradoPor', [
                    'path' => $firmaElabora,
                    'width' => 82.7,
                    'height' => 40,
                    'ratio' => true,
                ]);
            } else {
                $template->setValue('firmaElaboradoPor', '');
            }

            $firmaAutoriza = $this->obtenerRutaFirma($personal?->Id_autoriza);
            if ($firmaAutoriza && file_exists($firmaAutoriza)) {
                $template->setImageValue('firmaAutorizadoPor', [
                    'path' => $firmaAutoriza,
                    'width' => 82.7,
                    'height' => 40,
                    'ratio' => true,
                ]);
            } else {
                $template->setValue('firmaAutorizadoPor', '');
            }

            // Guardar temporal en storage/app/public/temp/
            $tempDir = storage_path('app/public/temp');
            if (! file_exists($tempDir)) {
                @mkdir($tempDir, 0777, true);
            }
            $tempDocx = $tempDir.'/'.$claveCompleta.'.docx';
            $template->saveAs($tempDocx);

            $pdfOutputDir = storage_path('app/public/pdf');
            if (! file_exists($pdfOutputDir)) {
                @mkdir($pdfOutputDir, 0777, true);
            }

            $targetPdf = $pdfOutputDir.'/'.$claveCompleta.'.pdf';
            if (file_exists($targetPdf)) {
                @unlink($targetPdf);
            }

            // Localizar ejecutable de LibreOffice
            $soffice = 'soffice';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                if (file_exists('C:\\Program Files\\LibreOffice\\program\\soffice.exe')) {
                    $soffice = '"C:\\Program Files\\LibreOffice\\program\\soffice.exe"';
                } elseif (file_exists('C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe')) {
                    $soffice = '"C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe"';
                }
            } else {
                if (file_exists('/usr/bin/soffice')) {
                    $soffice = 'HOME=/tmp /usr/bin/soffice';
                }
            }

            $cmd = "$soffice --headless --convert-to pdf --outdir \"$pdfOutputDir\" \"$tempDocx\" 2>&1";
            exec($cmd, $output, $retVal);

            if (file_exists($tempDocx)) {
                @unlink($tempDocx);
            }

            if (file_exists($targetPdf)) {
                return url('storage/pdf/'.$claveCompleta.'.pdf').'?v='.time();
            }
        } catch (\Throwable $e) {
            \Log::error('Error compilando Word a PDF con LibreOffice: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Resolver la ruta física del archivo de firma.
     */
    protected function obtenerRutaFirma(?int $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        $candidates = [
            storage_path('app/public/firmas/'.$userId.'.png'),
            public_path('firmas/'.$userId.'.png'),
            public_path('assets/images/firmas/'.$userId.'.png'),
        ];

        foreach ($candidates as $c) {
            if (file_exists($c)) {
                return $c;
            }
        }

        $detalle = DetalleUsuarios::where('Id_usuario', $userId)->first();
        if ($detalle && $detalle->Firma) {
            $firmaClean = str_replace('public/', '', $detalle->Firma);
            $path = storage_path('app/public/'.$firmaClean);
            if (file_exists($path)) {
                return $path;
            }
            if (file_exists(public_path($detalle->Firma))) {
                return public_path($detalle->Firma);
            }
        }

        return null;
    }

    /**
     * Generar vista oficial completa en HTML para documentos editados con el editor interno (Tipo 2).
     */
    public function generarDocumentoHtml($id)
    {
        $doc = Documentos::find($id);
        if (! $doc) {
            return response('Documento no encontrado', 404);
        }

        $procedimiento = Procedimientos::find($doc->Id_procedimiento);
        $detalle = DetalleDocumentos::where('Id_documento', $doc->Id_documento)->first();
        $personal = PersonalDocumentos::where('Id_documento', $doc->Id_documento)->first();

        $elabora = $personal ? DetalleUsuarios::where('Id_usuario', $personal->Id_elabora)->first() : null;
        $autoriza = $personal ? DetalleUsuarios::where('Id_usuario', $personal->Id_autoriza)->first() : null;

        $docDepto = DocumentosDepartamentos::where('Id_documento', $doc->Id_documento)->where('Id_departamento', '!=', 1)->first();
        $departamento = $docDepto ? Departamentos::find($docDepto->Id_departamento) : Departamentos::find(1);

        $claveCompleta = ($procedimiento ? $procedimiento->Clave_procedimiento : '').'-'.$doc->Clave_documento;

        $firmaElaboraUrl = null;
        $firmaElaboraPath = $this->obtenerRutaFirma($personal?->Id_elabora);
        if ($firmaElaboraPath && file_exists($firmaElaboraPath)) {
            $firmaElaboraUrl = 'data:image/png;base64,'.base64_encode(file_get_contents($firmaElaboraPath));
        }

        $firmaAutorizaUrl = null;
        $firmaAutorizaPath = $this->obtenerRutaFirma($personal?->Id_autoriza);
        if ($firmaAutorizaPath && file_exists($firmaAutorizaPath)) {
            $firmaAutorizaUrl = 'data:image/png;base64,'.base64_encode(file_get_contents($firmaAutorizaPath));
        }

        $logoPath = public_path('assets/images/logo-letras.png');
        $logoSrc = file_exists($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : url('assets/images/logo-letras.png');

        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>'.htmlspecialchars($doc->Documento).'</title>
    <link rel="stylesheet" href="'.url('assets/css/pdf.css').'">
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; margin: 0; padding: 25px 35px; color: #1e293b; background: #ffffff; }
        .tabla { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #000; }
        .tabla td { border: 1px solid #000; padding: 6px 10px; font-size: 12px; }
        .contenedor-logo { width: 120px; text-align: center; vertical-align: middle; }
        .contenedor-titulo { text-align: center; vertical-align: middle; }
        .contenedor-titulo h2 { margin: 0; font-size: 15px; font-weight: 700; color: #000; text-transform: uppercase; }
        .tabla-firmas { width: 100%; border-collapse: collapse; margin-bottom: 25px; border: 1px solid #000; }
        .tabla-firmas td { width: 50%; border: 1px solid #000; padding: 12px; text-align: center; vertical-align: top; }
        .etiqueta { font-size: 11px; font-weight: bold; text-align: left; margin: 0 0 4px 0; color: #334155; }
        .firma-img { height: 42px; max-width: 130px; object-fit: contain; display: block; margin: 0 auto; }
        .linea-firmas { border-top: 1px solid #000; width: 80%; margin: 8px auto 4px auto; }
        .nombre-firmas { font-size: 12px; font-weight: 600; margin: 0; color: #000; }
        .contenido-cuerpo { font-size: 13px; line-height: 1.6; margin-bottom: 35px; min-height: 300px; }
        .contenido-cuerpo p { margin-bottom: 10px; }
        .contenido-cuerpo table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        .contenido-cuerpo table, .contenido-cuerpo th, .contenido-cuerpo td { border: 1px solid #94a3b8; padding: 6px 10px; font-size: 12px; }
        .contenido-cuerpo th { background: #f8fafc; font-weight: 600; }
        .footer-legal { border-top: 1px solid #000; padding-top: 8px; font-size: 11px; color: #475569; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body oncontextmenu="return false;">
    <!-- Encabezado Oficial Institucional -->
    <table class="tabla">
        <tr>
            <td rowspan="5" class="contenedor-logo">
                <img src="'.$logoSrc.'" alt="Logo ACAMA" style="max-height: 40px; max-width: 110px;">
            </td>
            <td rowspan="5" class="contenedor-titulo">
                <h2>'.htmlspecialchars($doc->Documento).'</h2>
            </td>
            <td style="width: 125px; font-weight: 600;">Procedimiento No.</td>
            <td style="width: 130px; font-weight: 700; text-align: center;">'.htmlspecialchars($claveCompleta).'</td>
        </tr>
        <tr>
            <td style="font-weight: 600;">Válido desde:</td>
            <td style="text-align: center;">'.htmlspecialchars($doc->Valido_desde ?? '—').'</td>
        </tr>
        <tr>
            <td style="font-weight: 600;">Última Revisión:</td>
            <td style="text-align: center;">'.htmlspecialchars($doc->Ultima_modificacion ?? '—').'</td>
        </tr>
        <tr>
            <td style="font-weight: 600;">Revisión:</td>
            <td style="text-align: center; font-weight: bold;">'.htmlspecialchars($detalle?->Numero_revision ?? '00').'</td>
        </tr>
        <tr>
            <td style="font-weight: 600;">Departamento:</td>
            <td style="text-align: center;">'.htmlspecialchars($departamento?->Departamento ?? 'General').'</td>
        </tr>
    </table>

    <!-- Firmas Autorizadas -->
    <table class="tabla-firmas">
        <tr>
            <td>
                <p class="etiqueta">Elaborado por:</p>
                '.($firmaElaboraUrl ? '<img src="'.$firmaElaboraUrl.'" class="firma-img" alt="Firma">' : '<div style="height: 42px;"></div>').'
                <div class="linea-firmas"></div>
                <p class="nombre-firmas">'.htmlspecialchars($elabora?->Nombre ?? 'N/A').'</p>
            </td>
            <td>
                <p class="etiqueta">Autorizado por:</p>
                '.($firmaAutorizaUrl ? '<img src="'.$firmaAutorizaUrl.'" class="firma-img" alt="Firma">' : '<div style="height: 42px;"></div>').'
                <div class="linea-firmas"></div>
                <p class="nombre-firmas">'.htmlspecialchars($autoriza?->Nombre ?? 'N/A').'</p>
            </td>
        </tr>
    </table>

    <!-- Contenido Redactado en el Editor -->
    <div class="contenido-cuerpo">
        '.($detalle?->Contenido ?: '<p style="color: #94a3b8; font-style: italic;">Sin contenido redactado en el procedimiento.</p>').'
    </div>

    <!-- Pie de Página Oficial -->
    <div class="footer-legal">
        <span>'.htmlspecialchars($departamento?->Departamento ?? 'General').' | AcamaDocs</span>
        <span>Laboratorio de Análisis de Calidad del Agua y Medio Ambiente, S.A. de C.V.</span>
    </div>
</body>
</html>';

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Confirmar que el usuario ha revisado el documento.
     */
    public function confirmarRevision(Request $request, $id): JsonResponse
    {
        $userId = $request->user()?->Id_user ?? $request->input('id_usuario');

        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'Usuario no identificado'], 401);
        }

        $registro = DocumentosUsuarios::where('Id_documento', $id)
            ->where('Id_usuario', $userId)
            ->first();

        if ($registro) {
            $registro->Estado_revision = 0;
            $registro->Estado_lectura = 1;
            $registro->Porcentaje_lectura = 100;
            $registro->save();
        } else {
            DocumentosUsuarios::create([
                'Id_documento' => $id,
                'Id_usuario' => $userId,
                'Estado_revision' => 0,
                'Estado_lectura' => 1,
                'Porcentaje_lectura' => 100,
                'Id_user_c' => $userId,
                'Id_user_m' => $userId,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Documento marcado como revisado exitosamente',
            'estado' => 'exito',
        ]);
    }

    /**
     * Descargar el archivo del documento. Restringido exclusivamente al Administrador.
     */
    public function descargarDocumento(Request $request, $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $userDetalle = DetalleUsuarios::where('Id_usuario', $user->Id_user)->first();
        $isAdmin = ($user->Rol === 'Administrador') || ($userDetalle && $userDetalle->Id_departamento == 1);

        if (! $isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Descarga denegada: Los documentos no se pueden descargar. Únicamente los administradores tienen esa facultad.',
            ], 403);
        }

        $doc = Documentos::find($id);
        if (! $doc) {
            return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 404);
        }

        $procedimiento = Procedimientos::find($doc->Id_procedimiento);
        $claveCompleta = ($procedimiento ? $procedimiento->Clave_procedimiento : '').'-'.$doc->Clave_documento;

        $detalle = DetalleDocumentos::where('Id_documento', $doc->Id_documento)->first();

        // Si es tipo WORD (3), descargar el PDF compilado
        if ($doc->Id_tipo == 3) {
            $pdfPath = storage_path('app/public/pdf/'.$claveCompleta.'.pdf');
            if (file_exists($pdfPath)) {
                return response()->download($pdfPath, $claveCompleta.'.pdf');
            }
        }

        if (! $detalle || ! $detalle->Url) {
            return response()->json(['success' => false, 'message' => 'El documento no tiene un archivo físico asociado'], 404);
        }

        $relativePath = str_replace('public/', '', $detalle->Url);
        $fullPath = storage_path('app/public/'.$relativePath);

        if (! file_exists($fullPath)) {
            $publicCandidate = public_path($detalle->Url);
            if (file_exists($publicCandidate)) {
                $fullPath = $publicCandidate;
            } else {
                return response()->json(['success' => false, 'message' => 'El archivo físico no se encuentra en el servidor'], 404);
            }
        }

        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $downloadName = $claveCompleta.'.'.($extension ?: 'pdf');

        return response()->download($fullPath, $downloadName);
    }
}
