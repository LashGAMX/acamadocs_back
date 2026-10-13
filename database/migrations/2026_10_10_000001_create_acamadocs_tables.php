<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('departamentos')) {
            Schema::create('departamentos', function (Blueprint $table) {
                $table->id('Id_departamento');
                $table->string('Departamento');
                $table->text('Descripcion')->nullable();
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('procedimientos')) {
            Schema::create('procedimientos', function (Blueprint $table) {
                $table->id('Id_procedimiento');
                $table->string('Procedimiento');
                $table->string('Clave_procedimiento');
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('tipo_documentos')) {
            Schema::create('tipo_documentos', function (Blueprint $table) {
                $table->id('Id_tipo');
                $table->string('Tipo');
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('usuarios')) {
            Schema::create('usuarios', function (Blueprint $table) {
                $table->id('Id_user');
                $table->string('User')->unique();
                $table->string('Pass');
                $table->string('Rol')->nullable();
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('detalle_usuarios')) {
            Schema::create('detalle_usuarios', function (Blueprint $table) {
                $table->id('Id_detalle');
                $table->unsignedBigInteger('Id_usuario');
                $table->unsignedBigInteger('Id_departamento');
                $table->string('Nombre');
                $table->string('Firma')->nullable();
                $table->string('Foto_perfil')->nullable();
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('documentos')) {
            Schema::create('documentos', function (Blueprint $table) {
                $table->id('Id_documento');
                $table->unsignedBigInteger('Id_procedimiento');
                $table->unsignedBigInteger('Id_tipo');
                $table->string('Documento');
                $table->text('Descripcion')->nullable();
                $table->string('Clave_documento');
                $table->date('Valido_desde')->nullable();
                $table->date('Ultima_modificacion')->nullable();
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('detalle_documentos')) {
            Schema::create('detalle_documentos', function (Blueprint $table) {
                $table->id('Id_detalle');
                $table->unsignedBigInteger('Id_documento');
                $table->longText('Contenido')->nullable();
                $table->string('Url')->nullable();
                $table->integer('Numero_revision')->default(0);
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('personal_documentos')) {
            Schema::create('personal_documentos', function (Blueprint $table) {
                $table->id('Id_personal');
                $table->unsignedBigInteger('Id_documento');
                $table->unsignedBigInteger('Id_elabora');
                $table->unsignedBigInteger('Id_autoriza');
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('documentos_departamentos')) {
            Schema::create('documentos_departamentos', function (Blueprint $table) {
                $table->id('Id_documento_departamento');
                $table->unsignedBigInteger('Id_documento');
                $table->unsignedBigInteger('Id_departamento');
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('menus_usuarios')) {
            Schema::create('menus_usuarios', function (Blueprint $table) {
                $table->id('Id_menu');
                $table->unsignedBigInteger('Id_usuario');
                $table->unsignedBigInteger('Id_departamento')->nullable();
                $table->unsignedBigInteger('Id_procedimiento')->nullable();
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('documentos_usuarios')) {
            Schema::create('documentos_usuarios', function (Blueprint $table) {
                $table->id('Id_documento_usuario');
                $table->unsignedBigInteger('Id_documento');
                $table->unsignedBigInteger('Id_usuario');
                $table->integer('Estado_revision')->default(1);
                $table->integer('Estado_lectura')->default(0);
                $table->float('Porcentaje_lectura')->nullable();
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('catalogo_plantillas')) {
            Schema::create('catalogo_plantillas', function (Blueprint $table) {
                $table->id('Id_catalogo');
                $table->string('Catalogo');
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('plantillas')) {
            Schema::create('plantillas', function (Blueprint $table) {
                $table->id('Id_plantilla');
                $table->unsignedBigInteger('Id_catalogo');
                $table->string('Plantilla');
                $table->text('Descripcion')->nullable();
                $table->unsignedBigInteger('Id_user_c')->nullable();
                $table->unsignedBigInteger('Id_user_m')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        DB::statement("DROP VIEW IF EXISTS ViewDocumentos");
        DB::statement("CREATE VIEW ViewDocumentos AS 
            SELECT doc.*, tipo.Tipo, pro.Procedimiento, pro.Clave_procedimiento, det.Contenido, det.Url, det.Numero_revision,
                   per.Id_elabora, per.Id_autoriza, detUser1.Nombre as ElaboroName, detUser2.Nombre as AutorizoName
            FROM documentos as doc
            INNER JOIN tipo_documentos as tipo ON doc.Id_tipo = tipo.Id_tipo
            INNER JOIN procedimientos as pro ON doc.Id_procedimiento = pro.Id_procedimiento
            INNER JOIN detalle_documentos as det ON doc.Id_documento = det.Id_documento
            INNER JOIN personal_documentos as per ON doc.Id_documento = per.Id_documento
            INNER JOIN detalle_usuarios as detUser1 ON per.Id_elabora = detUser1.Id_usuario
            INNER JOIN detalle_usuarios as detUser2 ON per.Id_autoriza = detUser2.Id_usuario
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS ViewDocumentos");
        Schema::dropIfExists('plantillas');
        Schema::dropIfExists('catalogo_plantillas');
        Schema::dropIfExists('documentos_usuarios');
        Schema::dropIfExists('menus_usuarios');
        Schema::dropIfExists('documentos_departamentos');
        Schema::dropIfExists('personal_documentos');
        Schema::dropIfExists('detalle_documentos');
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('detalle_usuarios');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('tipo_documentos');
        Schema::dropIfExists('procedimientos');
        Schema::dropIfExists('departamentos');
    }
};
