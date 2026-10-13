<?php

use App\Http\Controllers\Api\AsignacionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartamentoController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\ProcedimientoController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VisorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - AcamaDocs
|--------------------------------------------------------------------------
*/

// Rutas de Autenticación Pública
Route::post('/login', [AuthController::class, 'login']);
Route::post('/authenticate', [AuthController::class, 'login']); // compatibilidad

// Rutas de Exportación y Visor accesibles directamente
Route::get('/documentos/exportar/{procedimientos?}', [DocumentoController::class, 'exportExcel']);
Route::get('/documentosAdmin/vistaExcel/{procedimientos?}', [DocumentoController::class, 'exportExcel']);

// Rutas protegidas por Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Perfil
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Documentos (REST + compatibilidad de rutas legadas)
    Route::get('/documentos', [DocumentoController::class, 'index']);
    Route::post('/documentos/getDocumentos', [DocumentoController::class, 'index']);
    Route::post('/documentosAdmin/getDocumentos', [DocumentoController::class, 'index']);
    Route::post('/documentosAdmin/getDocumentosParametros', [DocumentoController::class, 'index']);
    Route::post('/documentosAdmin/getSingleDocumentos', [DocumentoController::class, 'show']);

    Route::get('/documentos/mis-documentos', [DocumentoController::class, 'misDocumentos']);
    Route::post('/documentos/getDocumentosUsuarios', [DocumentoController::class, 'misDocumentos']);

    Route::post('/documentos', [DocumentoController::class, 'store']);
    Route::post('/documentosAdmin/crearDocumento/setDocumentos', [DocumentoController::class, 'store']);

    Route::get('/documentos/{id}', [DocumentoController::class, 'show']);
    Route::put('/documentos/{id}', [DocumentoController::class, 'update']);
    Route::post('/documentos/{id}', [DocumentoController::class, 'update']); // para multipart form-data con archivos
    Route::post('/documentosAdmin/modificarDocumento/upDocumentos', [DocumentoController::class, 'update']);

    Route::delete('/documentos/{id}', [DocumentoController::class, 'destroy']);
    Route::post('/documentosAdmin/modificarDocumento/delDocumentos', [DocumentoController::class, 'destroy']);

    // Visor y Confirmación de Revisión
    Route::get('/visor/{id}', [VisorController::class, 'verDocumento']);
    Route::get('/visor/{id}/html', [VisorController::class, 'generarDocumentoHtml']);
    Route::get('/visor/{id}/descargar', [VisorController::class, 'descargarDocumento']);
    Route::post('/documentosAdmin/getVerDocumento', function (Request $request) {
        return app(VisorController::class)->verDocumento($request, $request->input('id_documento'));
    });
    Route::post('/visor/{id}/confirmar', [VisorController::class, 'confirmarRevision']);
    Route::post('/usuarios/upRevisionUsuario', [AsignacionController::class, 'upRevisionUsuario']);

    // Procedimientos y Tipos de Documento
    Route::get('/procedimientos', [ProcedimientoController::class, 'index']);
    Route::post('/procedimientos/getProcedimientos', [ProcedimientoController::class, 'index']);
    Route::post('/procedimientos', [ProcedimientoController::class, 'store']);
    Route::put('/procedimientos/{id}', [ProcedimientoController::class, 'update']);
    Route::get('/tipos-documentos', [ProcedimientoController::class, 'tipos']);

    // Departamentos
    Route::get('/departamentos', [DepartamentoController::class, 'index']);
    Route::post('/departamentos/getDepartamentos', [DepartamentoController::class, 'index']);
    Route::post('/departamentos', [DepartamentoController::class, 'store']);
    Route::post('/departamentos/setDepartamentos', [DepartamentoController::class, 'store']);
    Route::put('/departamentos/{id}', [DepartamentoController::class, 'update']);
    Route::post('/departamentos/upDepartamentos', [DepartamentoController::class, 'update']);
    Route::delete('/departamentos/{id}', [DepartamentoController::class, 'destroy']);
    Route::post('/departamentos/delDepartamentos', [DepartamentoController::class, 'destroy']);

    // Usuarios
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios/getUsuariosConDetalles', [UsuarioController::class, 'index']);
    Route::post('/usuarios/getSingleUsuariosConDetalles', function (Request $request) {
        return app(UsuarioController::class)->show($request->input('id_usuario'));
    });
    Route::get('/usuarios/{id}', [UsuarioController::class, 'show']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::post('/usuarios/setUsuarios', [UsuarioController::class, 'store']);
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
    Route::post('/usuarios/{id}', [UsuarioController::class, 'update']);
    Route::post('/usuarios/upUsuarios', [UsuarioController::class, 'update']);
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy']);
    Route::post('/usuarios/delUsuarios', [UsuarioController::class, 'destroy']);

    // Asignaciones y Control de Revisiones
    Route::post('/usuarios/getDocumentosAsignados', [AsignacionController::class, 'getDocumentosAsignados']);
    Route::post('/usuarios/setDocumentoIndividual', [AsignacionController::class, 'setDocumentoIndividual']);
    Route::post('/usuarios/delDocumentoIndividual', [AsignacionController::class, 'delDocumentoIndividual']);
    Route::post('/usuarios/getMenusAsignados', [AsignacionController::class, 'getMenusAsignados']);
    Route::post('/usuarios/setDocumentosMenu', [AsignacionController::class, 'setDocumentosMenu']);
    Route::post('/usuarios/delDocumentosMenu', [AsignacionController::class, 'delDocumentosMenu']);
    Route::post('/usuarios/upRevision', [AsignacionController::class, 'upRevision']);
    Route::post('/usuarios/upRevisionTodos', [AsignacionController::class, 'upRevisionTodos']);
    Route::post('/usuarios/getEstadoRevisiones', [AsignacionController::class, 'getEstadoRevisiones']);
    Route::post('/usuarios/getEstadoRevisionesTodos', [AsignacionController::class, 'getEstadoRevisionesTodos']);
});
