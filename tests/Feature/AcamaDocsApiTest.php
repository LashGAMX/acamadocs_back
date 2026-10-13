<?php

namespace Tests\Feature;

use App\Models\Usuarios;
use Tests\TestCase;

class AcamaDocsApiTest extends TestCase
{
    public function test_login_api_returns_token_and_user_data(): void
    {
        $response = $this->postJson('/api/login', [
            'correo' => 'admin',
            'contrasena' => 'admin123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'username',
                    'rol',
                    'is_admin',
                    'nombre',
                ],
            ]);
    }

    public function test_procedimientos_and_departamentos_endpoints(): void
    {
        $usuario = Usuarios::where('User', 'admin')->first();
        $this->actingAs($usuario, 'sanctum');

        $resDeptos = $this->getJson('/api/departamentos');
        $resDeptos->assertStatus(200)->assertJson(['success' => true]);

        $resProc = $this->getJson('/api/procedimientos');
        $resProc->assertStatus(200)->assertJson(['success' => true]);

        $resDocs = $this->getJson('/api/documentos');
        $resDocs->assertStatus(200)->assertJson(['success' => true]);
    }
}
