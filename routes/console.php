<?php

use App\Models\Departamentos;
use App\Models\DetalleUsuarios;
use App\Models\Procedimientos;
use App\Models\TipoDocumentos;
use App\Models\Usuarios;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('check:acamadocs', function () {
    $this->info("=== Analizando siste168_acamasys_test ===");
    try {
        $pdoTest = new PDO('mysql:host=100.121.6.50;port=3306;dbname=siste168_acamasys_test', 'sofia', 'Sofia100%');
        $tables = $pdoTest->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $withData = [];
        foreach ($tables as $t) {
            $cnt = $pdoTest->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            if ($cnt > 0) {
                $withData[$t] = $cnt;
            }
        }
        $this->info("Tablas con datos en siste168_acamasys_test: " . count($withData));
        foreach ($withData as $tbl => $cnt) {
            $this->line("  $tbl: $cnt filas");
        }
    } catch (\Throwable $e) {
        $this->error("Error en test db: " . $e->getMessage());
    }

    $this->info("\n=== Analizando siste168_acamasys (tablas con datos) ===");
    try {
        $pdo = new PDO('mysql:host=100.121.6.50;port=3306;dbname=siste168_acamasys', 'sofia', 'Sofia100%');
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $withData = [];
        foreach ($tables as $t) {
            $cnt = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            if ($cnt > 0) {
                $withData[$t] = $cnt;
            }
        }
        $this->info("Total tablas con datos en siste168_acamasys: " . count($withData));
        foreach ($withData as $tbl => $cnt) {
            if (str_contains($tbl, 'doc') || str_contains($tbl, 'user') || str_contains($tbl, 'dep') || str_contains($tbl, 'proc') || str_contains($tbl, 'plan')) {
                $this->line("  $tbl: $cnt filas");
            }
        }
    } catch (\Throwable $e) {
        $this->error("Error en siste168_acamasys: " . $e->getMessage());
    }
});

Artisan::command('seed:acamadocs', function () {
    // 1. Tipos de Documento
    $tipos = [
        1 => 'PDF',
        2 => 'HTML',
        3 => 'WORD',
    ];
    foreach ($tipos as $id => $nombre) {
        if (! TipoDocumentos::find($id)) {
            DB::table('tipo_documentos')->insertOrIgnore([
                'Id_tipo' => $id,
                'Tipo' => $nombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("✓ Tipo creado: $nombre");
        }
    }

    // 2. Departamentos
    $deptos = [
        1 => ['Administración', 'Dirección y Control General'],
        2 => ['Calidad', 'Aseguramiento de Calidad'],
        3 => ['Operaciones', 'Operación de Campo y Laboratorio'],
    ];
    foreach ($deptos as $id => $info) {
        if (! Departamentos::find($id)) {
            DB::table('departamentos')->insertOrIgnore([
                'Id_departamento' => $id,
                'Departamento' => $info[0],
                'Descripcion' => $info[1],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("✓ Departamento creado: {$info[0]}");
        }
    }

    // 3. Procedimientos
    $procedimientos = [
        ['Manual de Calidad', 'MC'],
        ['Procedimiento General', 'PG'],
        ['Procedimiento Específico', 'PE'],
        ['Instrucción de Trabajo', 'IT'],
        ['Formato', 'FO'],
    ];
    foreach ($procedimientos as $p) {
        $existe = Procedimientos::where('Clave_procedimiento', $p[1])->first();
        if (! $existe) {
            Procedimientos::create([
                'Procedimiento' => $p[0],
                'Clave_procedimiento' => $p[1],
            ]);
            $this->info("✓ Procedimiento creado: {$p[1]} - {$p[0]}");
        }
    }

    // 4. Usuario Admin
    $userAdmin = Usuarios::where('User', 'admin')->first();
    if (! $userAdmin) {
        $userAdmin = Usuarios::create([
            'User' => 'admin',
            'Pass' => Hash::make('admin123'),
            'Rol' => 'Administrador',
        ]);
        DetalleUsuarios::create([
            'Id_usuario' => $userAdmin->Id_user,
            'Id_departamento' => 1,
            'Nombre' => 'Administrador General',
        ]);
        $this->info('✓ Usuario admin creado: admin / admin123');
    } else {
        $this->info('Usuario admin ya existe.');
    }

    $this->info('Catálogos y usuario administrador listos para AcamaDocs.');
});
