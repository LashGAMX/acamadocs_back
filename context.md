# ACAMADOCS - DOCUMENTO DE CONTEXTO Y ARQUITECTURA DE MIGRACIÓN
> **Sistema de Gestión Documental - Laboratorio de Análisis de Calidad del Agua y Medio Ambiente, S.A. de C.V. (ACAMA)**  
> **Propósito:** Guía de referencia técnica y funcional completa del sistema heredado (`e:\acamadocs`) para su migración y desacoplamiento en Backend (Laravel API) y Frontend (React SPA).

---

## 1. VISIÓN GENERAL DEL SISTEMA

### 1.1 Objetivo del Negocio
AcamaDocs es la plataforma central de control documental de calidad del laboratorio ACAMA. Su función es administrar el ciclo de vida de los documentos normativos y operativos (manuales de calidad, procedimientos generales, procedimientos específicos, formatos, instructivos), control de versiones, firmas autorizadas, distribución controlada por departamentos/usuarios y seguimiento de lecturas y revisiones obligatorias por parte del personal.

### 1.2 Problema que Resuelve la Migración
El sistema actual es un monolito en Laravel 11 con vistas Blade, scripts jQuery dispersos, Bootstrap / Hope UI acoplado, manipulación directa del DOM y renderizado de componentes vía llamadas AJAX no estandarizadas.
**Meta de la modernización:**
1. **Mantener íntegra la estructura de datos y las reglas de negocio** existentes para no perder información histórica ni compatibilidad.
2. **Dividir en Frontend (React) y Backend (Laravel RESTful API)**.
3. **Mejorar radicalmente la interfaz y experiencia de usuario (UI/UX)**, haciéndola moderna, reactiva, accesible y eficiente.

---

## 2. ARQUITECTURA Y MODELO DE DATOS

El sistema utiliza MySQL (`sistema_gestion`). Todas las tablas principales implementan marcas de auditoría (`Id_user_c`, `Id_user_m`), marcas de tiempo (`created_at`, `updated_at`) y borrado suave (`deleted_at` / `SoftDeletes`).

### 2.1 Diccionario de Datos

#### Tabla: `usuarios` (Autenticación y Credenciales)
- `Id_user` (BIGINT UNSIGNED, PK, AUTO_INCREMENT): Identificador único del usuario.
- `User` (VARCHAR): Correo electrónico o identificador de acceso (usado en login).
- `Pass` (VARCHAR): Contraseña encriptada (bcrypt / cast `hashed`).
- `Rol` (VARCHAR, Nullable): Rol reservado (la lógica principal de roles se basa en el departamento asociado).
- `Id_user_c` (INT, Nullable): ID de usuario creador.
- `Id_user_m` (INT, Nullable): ID de usuario modificador.
- `created_at`, `updated_at`, `deleted_at` (TIMESTAMP).

#### Tabla: `detalle_usuarios` (Perfil e Información Operativa)
- `Id_detalle` (BIGINT UNSIGNED, PK, AUTO_INCREMENT).
- `Id_usuario` (BIGINT UNSIGNED, FK -> `usuarios.Id_user`).
- `Id_departamento` (BIGINT UNSIGNED, FK -> `departamentos.Id_departamento`).
- `Nombre` (VARCHAR): Nombre completo y título profesional (ej. *I.A Alejandro Morales López*).
- `Firma` (VARCHAR, Nullable): Ruta relativa al archivo de firma gráfica (ej. `public/firmas/1.png`).
- `Foto_perfil` (VARCHAR, Nullable): Ruta relativa a foto de perfil (ej. `public/fperfil/1.png`).
- `Id_user_c`, `Id_user_m`, `created_at`, `updated_at`, `deleted_at`.

#### Tabla: `departamentos` (Catálogo de Departamentos)
- `Id_departamento` (BIGINT UNSIGNED, PK, AUTO_INCREMENT).
- `Departamento` (VARCHAR): Nombre del departamento (Valores especiales: `'Administrador'`, `'Sin Departamento'`).
- `Descripcion` (TEXT, Nullable): Descripción de funciones del área.
- `Id_user_c`, `Id_user_m`, `created_at`, `updated_at`, `deleted_at`.

#### Tabla: `procedimientos` (Familias de Procedimientos)
- `Id_procedimiento` (BIGINT UNSIGNED, PK, AUTO_INCREMENT).
- `Procedimiento` (VARCHAR): Nombre descriptivo de la categoría/procedimiento.
- `Clave_procedimiento` (VARCHAR): Código alfabético del procedimiento (ej. `MC` [Manual de Calidad], `PG` [Procedimiento General], `PE` [Procedimiento Específico], `DOE` [Documentos de Operación], etc.).
- `Id_user_c`, `Id_user_m`, `created_at`, `updated_at`, `deleted_at`.

#### Tabla: `tipo_documentos` (Tipos de Formato Soportados)
- `Id_tipo` (BIGINT UNSIGNED, PK, AUTO_INCREMENT):
  - **1**: `PDF` (archivo PDF estático subido por el usuario).
  - **2**: `MANUAL / FROALA` (contenido HTML enriquecido editado en línea y renderizado dinámicamente).
  - **3**: `WORD` (plantilla DOCX con marcas `${...}` combinada con datos del sistema y convertida a PDF).
- `Tipo` (VARCHAR): Nombre del tipo.
- `Id_user_c`, `Id_user_m`, `created_at`, `updated_at`, `deleted_at`.

#### Tabla: `documentos` (Cabecera de Documentos)
- `Id_documento` (BIGINT UNSIGNED, PK, AUTO_INCREMENT).
- `Id_procedimiento` (BIGINT UNSIGNED, FK -> `procedimientos.Id_procedimiento`).
- `Id_tipo` (BIGINT UNSIGNED, FK -> `tipo_documentos.Id_tipo`).
- `Documento` (VARCHAR): Título oficial del documento.
- `Descripcion` (TEXT, Nullable): Resumen o descripción del documento.
- `Clave_documento` (VARCHAR): Sufijo o código numérico/alfanumérico (ej. `01`, `02-01`, `999-102`).
  - *Nota:* La clave completa que identifica al documento es `{procedimientos.Clave_procedimiento}-{documentos.Clave_documento}`.
- `Valido_desde` (DATE): Fecha de entrada en vigor de la versión actual.
- `Ultima_modificacion` (DATE): Fecha de la última revisión o modificación.
- `Id_user_c`, `Id_user_m`, `created_at`, `updated_at`, `deleted_at`.

#### Tabla: `detalle_documentos` (Contenido y Versión del Documento)
- `Id_detalle` (BIGINT UNSIGNED, PK, AUTO_INCREMENT).
- `Id_documento` (BIGINT UNSIGNED, FK -> `documentos.Id_documento`).
- `Contenido` (LONGTEXT, Nullable): Código HTML generado para documentos de tipo 2.
- `Url` (VARCHAR, Nullable): Ruta relativa al archivo físico (`public/pdf/...` o `public/word/...`).
- `Numero_revision` (INT): Número entero secuencial de la revisión actual (ej. 0, 1, 2...).
- `Id_user_c`, `Id_user_m`, `created_at`, `updated_at`, `deleted_at`.

#### Tabla: `personal_documentos` (Firmas y Responsables)
- `Id_personal` (BIGINT UNSIGNED, PK, AUTO_INCREMENT).
- `Id_documento` (BIGINT UNSIGNED, FK -> `documentos.Id_documento`).
- `Id_elabora` (BIGINT UNSIGNED, FK -> `usuarios.Id_user`): Usuario responsable de elaborar el documento.
- `Id_autoriza` (BIGINT UNSIGNED, FK -> `usuarios.Id_user`): Usuario responsable de autorizar el documento.
- `Id_user_c`, `Id_user_m`, `created_at`, `updated_at`, `deleted_at`.

#### Tabla: `documentos_departamentos` (Distribución por Departamentos)
- `Id_documento_departamento` (BIGINT UNSIGNED, PK, AUTO_INCREMENT).
- `Id_documento` (BIGINT UNSIGNED, FK -> `documentos.Id_documento`).
- `Id_departamento` (BIGINT UNSIGNED, FK -> `departamentos.Id_departamento`).
- `Id_user_c`, `Id_user_m`, `created_at`, `updated_at`, `deleted_at`.

#### Tabla: `menus_usuarios` (Permisos y Grupos Asignados a Usuarios)
- `Id_menu` (BIGINT UNSIGNED, PK, AUTO_INCREMENT).
- `Id_usuario` (BIGINT UNSIGNED, FK -> `usuarios.Id_user`).
- `Id_departamento` (BIGINT UNSIGNED, Nullable): Si está presente, el usuario tiene asignados todos los documentos de ese departamento.
- `Id_procedimiento` (BIGINT UNSIGNED, Nullable): Si está presente, el usuario tiene asignados todos los documentos de esa familia de procedimiento.
- `Id_user_c`, `Id_user_m`, `created_at`, `updated_at`, `deleted_at`.

#### Tabla: `documentos_usuarios` (Estado Individual de Revisión y Lectura)
- `Id_documento_usuario` (BIGINT UNSIGNED, PK, AUTO_INCREMENT).
- `Id_documento` (BIGINT UNSIGNED, FK -> `documentos.Id_documento`).
- `Id_usuario` (BIGINT UNSIGNED, FK -> `usuarios.Id_user`).
- `Estado_revision` (INT):
  - `1`: **Necesita revisión** (el documento ha sido creado o actualizado y el usuario debe leerlo/confirmarlo).
  - `0`: **Revisado / Conforme** (el usuario ya confirmó la lectura de la revisión).
- `Estado_lectura` (INT): Bandera complementaria de estado de lectura (1 = leído).
- `Porcentaje_lectura` (FLOAT/INT, Nullable): Campo para seguimiento gradual.
- `Id_user_c`, `Id_user_m`, `created_at`, `updated_at`, `deleted_at`.

#### Tablas Complementarias
- `plantillas` / `catalogo_plantillas`: Estructura para gestión de plantillas normalizadas.
- `sessions`, `cache`, `jobs`: Tablas estándar del framework Laravel.

#### Vista SQL: `ViewDocumentos`
Consolida en una sola entidad:
```sql
SELECT 
    doc.*,
    tipo.Tipo,
    pro.Procedimiento,
    pro.Clave_procedimiento,
    det.Contenido,
    det.Url,
    det.Numero_revision,
    per.Id_elabora,
    per.Id_autoriza,
    detUser1.Nombre AS ElaboroName,
    detUser2.Nombre AS AutorizoName
FROM documentos AS doc
INNER JOIN tipo_documentos AS tipo ON doc.Id_tipo = tipo.Id_tipo
INNER JOIN procedimientos AS pro ON doc.Id_procedimiento = pro.Id_procedimiento
INNER JOIN detalle_documentos AS det ON doc.Id_documento = det.Id_documento
INNER JOIN personal_documentos AS per ON doc.Id_documento = per.Id_documento
INNER JOIN detalle_usuarios AS detUser1 ON per.Id_elabora = detUser1.Id_usuario
INNER JOIN detalle_usuarios AS detUser2 ON per.Id_autoriza = detUser2.Id_usuario;
```

---

## 3. LÓGICA DE NEGOCIO Y REGLAS OPERATIVAS

### 3.1 Clave Compuesta de Documentos
- Al dar de alta un documento, el usuario selecciona una clave de procedimiento (`Clave_procedimiento`, ej. `PG`) y captura hasta 4 segmentos de texto (`clave-2`, `clave-3`, `clave-4`, `clave-5`), que se unen en mayúsculas con guiones.
- Ejemplo: `PG` + `01` + `02` => `PG-01-02`.
- El sistema valida unicidad entre `Clave_documento` y `Id_procedimiento` (no eliminados por soft delete). Si ya existe, rechaza el registro con alerta.

### 3.2 Cascada de Notificaciones y Asignación de Revisión
1. **Creación de Documento (`setDocumentos`):**
   - Siempre se asigna automáticamente al departamento 1 (`Id_departamento = 1`, que corresponde a Administrador).
   - Se guarda en `documentos_departamentos` para cada departamento seleccionado en la UI.
   - Se consultan todos los usuarios que tengan ese departamento asignado o esa familia de procedimiento asignada en `menus_usuarios`.
   - Para cada usuario encontrado, se inserta un registro en `documentos_usuarios` con `Estado_revision = 1` (amarillo / requiere revisión).
2. **Edición de Documento (`upDocumentos`):**
   - Si se modifica cualquier dato relevante o archivo del documento, **todos los usuarios asignados pasan automáticamente a `Estado_revision = 1`**.
   - Si se modifican departamentos asociados:
     - Se eliminan de `documentos_usuarios` aquellos usuarios que ya no tengan acceso por ningún menú.
     - Se agregan a `documentos_usuarios` con `Estado_revision = 1` los usuarios de los nuevos departamentos agregados.
3. **Confirmación de Lectura por el Usuario (`upRevisionUsuario`):**
   - Cuando el usuario normal consulta el documento en `/documentos/verDocumento/{clave}`, la pantalla detecta si `revision == 1`.
   - Si es así, muestra un botón: *"Comprendo y he revisado las modificaciones en este procedimiento"*.
   - Al pulsar el botón, se llama a `upRevisionUsuario`, cambiando `Estado_revision = 0` y `Estado_lectura = 1`.
   - En la tabla de inicio del usuario, las filas con `Estado_revision = 1` tienen la clase CSS `.necesita-revision` (fondo amarillo claro `#FFFAEB` / `#FFFA81`) y un ícono de alerta; cuando está en `0`, se muestra palomita verde/neutra.

### 3.3 Gestión de Roles y Permisos
- El sistema diferencia dos roles principales basados en el departamento al que pertenece el usuario:
  1. **Administrador (`Id_departamento == 1` o nombre `'Administrador'`):**
     - Acceso al panel administrativo: `/documentosAdmin`, `/departamentos`, `/usuarios`, `/usuarios/documentosUsuarios`.
     - Puede crear, editar, eliminar documentos, cambiar archivos, administrar usuarios, firmas, fotos y asignar menús/documentos en lote.
     - Puede generar la **Lista Maestra de Documentos** en Excel.
  2. **Usuario Operativo (Cualquier otro departamento):**
     - Acceso exclusivo al portal: `/documentos`.
     - Solo puede ver los documentos que le han sido asignados explícitamente o por su departamento.
     - No puede modificar documentos ni acceder a rutas administrativas (se redirige a `/prohibido`).

### 3.4 Eliminaciones con Integridad Referencial
- **Al eliminar un Departamento (`delDepartamentos`):**
  - Los usuarios que pertenecían a ese departamento se reasignan al departamento `'Sin Departamento'` (o ID 1) para no dejarlos huérfanos.
  - Se eliminan las asignaciones en `documentos_departamentos` y `menus_usuarios`.
  - Se realiza soft delete del departamento.
- **Al eliminar un Usuario (`delUsuarios`):**
  - Si el usuario figuraba como `Id_elabora` o `Id_autoriza` en documentos históricos, dichos campos en `personal_documentos` se reasignan a un usuario comodín `'Sin Usuario'` (o ID 1) para preservar la integridad documental.
  - Se eliminan sus registros en `documentos_usuarios`.
  - Se eliminan físicamente los archivos de firma y foto si existían.
  - Se realiza soft delete del usuario y su detalle.

### 3.5 Generación y Previsualización de Documentos

#### Caso 1: Documento PDF (Tipo 1)
- El archivo se almacena en `storage/app/public/pdf/{Clave_procedimiento}-{Clave_documento}.pdf`.
- Se expone mediante el symlink `public/pdf/`.
- Se incrusta en un `<iframe>` con `#toolbar=0` para dificultar la descarga no autorizada y con menú contextual deshabilitado (`oncontextmenu="return false"`).

#### Caso 2: Documento Manual HTML (Tipo 2)
- El contenido redactado en el editor se almacena en `detalle_documentos.Contenido`.
- Al consultar `/generarDocumento/{clave}`, se ensambla dinámicamente un HTML con tres vistas Blade:
  - `layout.header`: membrete oficial de ACAMA con logotipo, título, clave de procedimiento, fechas y revisión.
  - `layout.layoutPdf`: firmas de elaboró y autorizó con sus respectivas imágenes PNG y líneas de firma, seguido del cuerpo HTML.
  - `layout.footer`: pie de página con paginación, departamento y leyenda de confidencialidad institucional.

#### Caso 3: Documento Word Template (Tipo 3)
- Se sube la plantilla base en `storage/app/public/word/{Clave_procedimiento}-{Clave_documento}.docx`.
- Al solicitar la visualización (`verDocumento`), el backend realiza:
  1. Instancia `PhpOffice\PhpWord\TemplateProcessor` sobre la plantilla.
  2. Inyecta los valores actuales:
     - `tituloDocumento`
     - `procedimientoNo`
     - `validoDesde`
     - `fechaUltimaRevision`
     - `revision`
     - `nombreElaboradoPor` / `nombreAutorizadoPor`
     - `departamento`
     - `firmaElaboradoPor` / `firmaAutorizadoPor` (incrusta la imagen PNG de la firma redimensionada).
  3. Guarda un docx temporal en `storage/app/public/temp/`.
  4. Ejecuta LibreOffice en segundo plano (`soffice --headless --convert-to pdf --outdir ...`) para compilar el DOCX a PDF.
  5. Elimina el archivo temporal DOCX.
  6. Sirve el PDF resultante en el iframe con control de caché (`Cache-Control: no-store, no-cache`).

### 3.6 Generación de Lista Maestra (Excel)
- Exporta la matriz oficial de control de documentos en formato `.xlsx` (`Maatwebsite\Excel`).
- Estructura del reporte:
  - Columnas fijas: `CLAVE`, `TÍTULO`, `VALIDO DESDE`, `ÚLTIMA REVISIÓN`, `NO. REVISIÓN`.
  - Columnas dinámicas: Una columna por cada departamento existente (excepto 'Sin Departamento'). Si el documento está distribuido a dicho departamento, se marca con una `"X"`.
- Permite filtrar qué procedimientos incluir pasando un arreglo JSON de IDs activos desde un modal con checkboxes.

### 3.7 Filtros de Vencimiento / Caducidad
En la vista administrativa se filtran documentos por vigencia para auditoría de calidad:
- **Estado 1 ("Por vencer"):** Documentos de procedimientos `'MC'`, `'PG'`, `'PE'` cuya `Ultima_modificacion` tenga entre 47 y 48 meses de antigüedad.
- **Estado 2 ("Vencido"):** Documentos cuya `Ultima_modificacion` sea superior a 48 meses (4 años).

---

## 4. CATÁLOGO COMPLETO DE RUTAS Y ENDPOINTS EXISTENTES

| Método | Ruta | Middleware | Controlador / Acción | Descripción / Payload |
|---|---|---|---|---|
| **GET** | `/` | `reedirigirSiAutenticado` | `LoginController@login` | Muestra vista de login o redirige según rol. |
| **POST** | `/authenticate` | - | `LoginController@authenticate` | Autenticación con `correo` y `contrasena`. Regenera sesión. |
| **GET** | `/logout` | - | `LoginController@logout` | Destruye sesión e invalida token. |
| **GET** | `/prohibido` | - | Closure | Vista 403 personalizada. |
| **GET** | `/documentosAdmin` | `rol:Administrador` | `ListaDocumentosController@documentosAdmin` | Bandeja principal de documentos para Admin. |
| **GET** | `/documentosAdmin/vistaExcel/{procedimientos}` | `rol:Administrador` | `ListaDocumentosController@vistaExcel` | Descarga de Excel de Lista Maestra. |
| **GET** | `/documentosAdmin/verDocumento/{clave}` | - | `ListaDocumentosController@verDocumento` | Visualizador de PDF (incluye conversión Word->PDF). |
| **POST** | `/documentosAdmin/getVerDocumento` | `autenticado` | `ListaDocumentosController@getVerDocumento` | Obtiene URL y título de documento por `id_documento`. |
| **POST** | `/documentosAdmin/getDocumentos` | `autenticado` | `ListaDocumentosController@getDocumentos` | Lista todos los documentos activos ordenados por ID desc. |
| **POST** | `/documentosAdmin/getDocumentosParametros` | `autenticado` | `ListaDocumentosController@getDocumentosParametros` | Filtro por `codigo`, `clave`, `titulo`, `estado` (por vencer/vencido). |
| **POST** | `/documentosAdmin/getSingleDocumentos` | `autenticado` | `ListaDocumentosController@getSingleDocumentos` | Detalle completo de un documento por `id_documento`. |
| **GET** | `/documentosAdmin/crearDocumento` | `rol:Administrador` | `ListaDocumentosController@crearDocumento` | Formulario de alta de documento. |
| **POST** | `/documentosAdmin/crearDocumento/setDocumentos` | `autenticado` | `ListaDocumentosController@setDocumentos` | Alta de documento (multipart: archivo, clave, firmas, deps). |
| **GET** | `/documentosAdmin/modificarDocumento/{clave}` | `rol:Administrador` | `ListaDocumentosController@modificarDocumento` | Formulario de edición de documento. |
| **POST** | `/documentosAdmin/modificarDocumento/upDocumentos` | `autenticado` | `ListaDocumentosController@upDocumentos` | Actualización de documento y reasignación de estados de revisión. |
| **POST** | `/documentosAdmin/modificarDocumento/delDocumentos` | `autenticado` | `ListaDocumentosController@delDocumentos` | Borrado suave de documento y limpieza de archivos físicos. |
| **GET** | `/documentos` | `rol:NoAdministrador` | `UsuarioController@documentos` | Bandeja de usuario normal (solo docs asignados). |
| **POST** | `/documentos/getDocumentosUsuarios` | `autenticado` | `ListaDocumentosController@getDocumentosUsuarios` | Lista docs del usuario con banderas `Estado_revision` y `Estado_lectura`. |
| **GET** | `/documentos/verDocumento/{clave}` | `rol:NoAdministrador` | `ListaDocumentosController@verDocumentoUser` | Visor para usuario con botón de confirmar revisión. |
| **GET** | `/departamentos` | `rol:Administrador` | `DepartamentosController@departamento` | Vista CRUD de departamentos. |
| **POST** | `/departamentos/getDepartamentos` | `rol:Administrador` | `DepartamentosController@getDepartamentos` | Lista todos los departamentos. |
| **POST** | `/departamentos/setDepartamentos` | `rol:Administrador` | `DepartamentosController@setDepartamentos` | Crear departamento (`departamento`, `descripcion`). |
| **POST** | `/departamentos/upDepartamentos` | `rol:Administrador` | `DepartamentosController@upDepartamentos` | Editar departamento (`id_departamento`, `departamento`, `descripcion`). |
| **POST** | `/departamentos/delDepartamentos` | `rol:Administrador` | `DepartamentosController@delDepartamentos` | Eliminar departamento con reasignación de usuarios. |
| **GET** | `/usuarios` | `rol:Administrador` | `UsuariosController@usuario` | Vista CRUD de usuarios. |
| **POST** | `/usuarios/getUsuariosConDetalles` | `autenticado` | `UsuariosController@getUsuariosConDetalles` | Lista usuarios con departamento, firma y foto. |
| **POST** | `/usuarios/getSingleUsuariosConDetalles` | `autenticado` | `UsuariosController@getSingleUsuariosConDetalles` | Detalle de un usuario específico. |
| **POST** | `/usuarios/setUsuarios` | `autenticado` | `UsuariosController@setUsuarios` | Alta de usuario (`correo`, `contrasena`, `nombre`, `departamento`, files). |
| **POST** | `/usuarios/upUsuarios` | `autenticado` | `UsuariosController@upUsuarios` | Edición de usuario (`id`, `correo`, password opcional, etc.). |
| **POST** | `/usuarios/delUsuarios` | `autenticado` | `UsuariosController@delUsuarios` | Baja de usuario con reasignación en `personal_documentos`. |
| **GET** | `/usuarios/documentosUsuarios` | `rol:Administrador` | `UsuariosController@documentosUsuarios` | Vista de asignación de documentos y menús a usuarios. |
| **POST** | `/usuarios/getDocumentosAsignados` | `autenticado` | `UsuariosController@getDocumentosAsignados` | Retorna dos listas: `asignados` y `noAsignados` para un usuario. |
| **POST** | `/usuarios/setDocumentoIndividual` | `autenticado` | `UsuariosController@setDocumentoIndividual` | Asigna un documento específico a un usuario (`Id_documento`, `Id_usuario`). |
| **POST** | `/usuarios/delDocumentoIndividual` | `autenticado` | `UsuariosController@delDocumentoIndividual` | Desasigna un documento individual de un usuario. |
| **POST** | `/usuarios/getMenusAsignados` | `autenticado` | `UsuariosController@getMenusAsignados` | Lista menús (deptos/procedimientos) asignados a un usuario. |
| **POST** | `/usuarios/setDocumentosMenu` | `autenticado` | `UsuariosController@setDocumentosMenu` | Asigna un menú completo (todos los docs de un depto o procedimiento). |
| **POST** | `/usuarios/delDocumentosMenu` | `autenticado` | `UsuariosController@delDocumentosMenu` | Quita un menú completo y remueve sus documentos del usuario. |
| **POST** | `/usuarios/upRevision` | `autenticado` | `UsuariosController@upRevision` | Alterna o asigna estado de revisión (Individual o Conjunto). |
| **POST** | `/usuarios/upRevisionTodos` | `autenticado` | `UsuariosController@upRevisionTodos` | Marca todos los documentos de todos los usuarios para revisión. |
| **POST** | `/usuarios/upRevisionUsuario` | `autenticado` | `UsuariosController@upRevisionUsuario` | Confirmación de lectura/revisión por parte del usuario. |
| **POST** | `/usuarios/getEstadoRevisiones` | `autenticado` | `UsuariosController@getEstadoRevisiones` | Consulta si el estado de revisión de un usuario es uniforme. |
| **POST** | `/usuarios/getEstadoRevisionesTodos` | `autenticado` | `UsuariosController@getEstadoRevisionesTodos` | Consulta si todos los documentos del sistema requieren revisión. |
| **POST** | `/usuarios/getDetalleUsuarios` | `autenticado` | `UsuariosController@getDetalleUsuarios` | Obtiene lista simple de usuarios para selects de firmas. |
| **POST** | `/procedimientos/getProcedimientos` | `autenticado` | `ProcedimientosController@getProcedimientos` | Obtiene catálogo completo de procedimientos. |
| **GET** | `/generarDocumento/{clave}` | `autenticado` | `ListaDocumentosController@generarDocumento` | Renderiza HTML del documento con membrete y firmas. |
| **GET** | `/generarDocumentoWord/{clave}` | `autenticado` | `ListaDocumentosController@generarDocumentoWord` | Ruta auxiliar de generación Word. |
| **GET** | `/controlDeDanos/arreglarDocumentosAsignados` | `rol:Administrador` | `ControlDeDanos@arreglarDocumentosAsignados` | Script de reconciliación de documentos faltantes por menú. |
| **GET** | `/importLista` | - | `HomeController@importLista` | Importador Excel de documentos (`lista.xlsx`). |

---

## 5. ESTRUCTURA Y COMPONENTES DEL FRONTEND ACTUAL

### 5.1 Estilos y Paleta de Colores
- **Color Primario (Verde Institucional ACAMA):** `#006552` / `#29A68F`
- **Color Secundario / Hover:** `#008B72` / `#D9E7E5`
- **Color de Modificación / Neutral Oscuro:** `#263331`
- **Color de Alerta / Necesita Revisión:** Amarillo suave `#FFFA81` / `#FFFAB5`
- **Tipografía Base:** `Inter`, `Roboto`, `Helvetica Neue`.

### 5.2 Dependencias Frontend Clave en el Monolito
- **Bootstrap 5.x + Hope UI 2.0**: Framework de diseño y componentes de layout (sidebar colapsable, navbar, modales, loaders).
- **jQuery 3.7.1**: Manipulación del DOM y peticiones asíncronas AJAX.
- **DataTables (`datatables.min.js`)**: Renderizado de tablas interactivas con paginación, búsqueda y ordenamiento del lado del cliente.
- **Select2 (`select2.min.js`)**: Selectores avanzados y selección múltiple de departamentos con autocompletado.
- **Froala Editor (`froala_editor.pkgd.min.js`)**: Editor WYSIWYG para documentos tipo 2 con inserción de tablas, imágenes (base64) y estilos de texto.

---

## 6. PLAN Y ESTRATEGIA DE MIGRACIÓN (FRONT + BACK)

### 6.1 Backend: Laravel 12 API (`acamadocs_back`)
1. **Autenticación y Sesiones:**
   - Implementar **Laravel Sanctum** para autenticación mediante Tokens Personales de Acceso (Bearer Token) o cookies SPA seguras.
   - Endpoint de login: `POST /api/v1/auth/login` retornando token, perfil de usuario, departamento y rol.
   - Endpoint de perfil: `GET /api/v1/auth/me`.
   - Endpoint de logout: `POST /api/v1/auth/logout`.
2. **Estandarización de Respuestas API:**
   - Creación de **API Resources** (`DocumentoResource`, `UsuarioResource`, `DepartamentoResource`, `ProcedimientoResource`).
   - Códigos de estado HTTP correctos (`200 OK`, `201 Created`, `422 Unprocessable Content`, `403 Forbidden`, `404 Not Found`).
   - Formato de respuesta JSON consistente:
     ```json
     {
       "success": true,
       "data": { ... },
       "message": "Operación exitosa"
     }
     ```
3. **Servicios Especializados:**
   - `DocumentProcessorService`: Encapsular la lógica de sustitución de variables en plantillas Word y conversión a PDF vía LibreOffice/Soffice.
   - `PdfGeneratorService`: Encapsular la generación de HTML imprimible con membrete y firmas digitales.
   - `DocumentAssignmentService`: Encapsular la lógica de propagación de revisiones y sincronización por departamentos/menús.
4. **Almacenamiento de Archivos (`Storage`):**
   - Usar el disco `public` o `local` protegido con rutas firmadas temporales (`temporarySignedRoute`) o streaming controlado para proteger los archivos PDF de descargas públicas directas.

### 6.2 Frontend: React SPA (`acamadocs_front`)
1. **Tecnologías Recomendadas:**
   - **Vite + React** (JavaScript o TypeScript).
   - **Enrutamiento:** `react-router-dom` con rutas protegidas (`ProtectedRoute`) según rol (`Administrador` vs `Operativo`).
   - **Gestión de Estado y Peticiones:** Axios configurado con interceptores para inyectar automáticamente el Bearer Token y capturar errores 401/403; React Query (TanStack Query) o Zustand para caché y estado global.
   - **UI / Sistema de Diseño:**
     - Diseño premium, moderno, rápido y responsive (manteniendo la paleta corporativa de ACAMA en verde `#006552` / `#29A68F`).
     - Componentes modernos para tablas (TanStack Table / DataTables modernos) con búsqueda, filtros, ordenamiento y paginación.
     - Modales modernos, alertas animadas (SweetAlert2 o Radix UI / Sonner toast notifications).
   - **Visualizador de Documentos:**
     - Integración de visor PDF nativo o `react-pdf` / `iframe` seguro con controles restringidos y opción de pantalla completa.
   - **Editor Enriquecido:**
     - Editor de contenido moderno y liviano (ej. TipTap o Froala React) compatible con el contenido HTML existente.

---

## 7. MATRIZ DE RIESGOS Y PUNTOS DE ATENCIÓN
1. **Dependencia de LibreOffice en el Servidor:**
   - La conversión de plantillas Word a PDF requiere que el servidor donde corre el backend tenga instalado LibreOffice (`soffice`). En Linux: `/usr/bin/soffice`; en Windows: `soffice.exe`. Debe configurarse la ruta del binario en `.env`.
2. **Imágenes de Firmas:**
   - Las firmas de los usuarios están almacenadas en `storage/app/public/firmas/{Id_usuario}.png`. Es vital preservar estos archivos y mantener los identificadores de usuario (`Id_user`) para que los documentos históricos continúen mostrando las firmas correctas.
3. **Mapeo de Claves y Segmentos:**
   - En el frontend nuevo, el formulario de creación y edición debe permitir tanto el desglose en segmentos (`clave-1` a `clave-5`) como la validación reactiva en tiempo real para evitar duplicados antes del envío.
4. **Caché de Documentos en el Navegador:**
   - En el sistema viejo se agregaba `?time()` o `?uniqid()` para evitar que el navegador cacheara un PDF modificado. En la API moderna se debe enviar encabezados `Cache-Control: no-cache, no-store, must-revalidate` y utilizar versionado por hash/timestamp.

---
*Este documento constituye la fuente de verdad técnica y funcional para la migración del proyecto.*
