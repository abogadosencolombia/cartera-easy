<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\NotaGestion;
use App\Livewire\GestionDiaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GestionDiariaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function un_abogado_puede_crear_una_nota_de_gestion()
    {
        $user = User::factory()->create(['tipo_usuario' => 'abogado']);
        $this->actingAs($user);

        Livewire::test(GestionDiaria::class)
            ->set('descripcion', 'REVISIÓN DE EXPEDIENTE')
            ->set('despacho', 'JUZGADO 01 CIVIL')
            ->set('termino', '24 HORAS')
            ->call('crearNota')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('nota_gestions', [
            'user_id' => $user->id,
            'descripcion' => 'REVISIÓN DE EXPEDIENTE',
            'despacho' => 'JUZGADO 01 CIVIL'
        ]);
    }

    /** @test */
    public function la_descripcion_despacho_y_termino_son_obligatorios()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(GestionDiaria::class)
            ->set('descripcion', '')
            ->set('despacho', '')
            ->set('termino', '')
            ->call('crearNota')
            ->assertHasErrors(['descripcion', 'despacho', 'termino']);
    }

    /** @test */
    public function un_abogado_solo_ve_sus_propias_notas()
    {
        $abogadoA = User::factory()->create();
        $abogadoB = User::factory()->create();

        NotaGestion::create([
            'user_id' => $abogadoA->id,
            'descripcion' => 'NOTA PRIVADA DE A',
            'despacho' => 'DESPACHO A',
            'termino' => 'HOY',
            'expires_at' => now()->addHours(8)
        ]);

        $this->actingAs($abogadoB);

        Livewire::test(GestionDiaria::class)
            ->assertViewHas('notas', function ($notas) {
                return $notas->count() === 0;
            });
    }

    /** @test */
    public function una_nota_se_crea_con_vencimiento_de_8_horas()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(GestionDiaria::class)
            ->set('descripcion', 'TEST TIEMPO')
            ->set('despacho', 'TEST')
            ->set('termino', 'TEST')
            ->call('crearNota');

        $nota = NotaGestion::first();
        
        // Verificamos que la diferencia sea de aproximadamente 8 horas (margen de error de 1 minuto por ejecución)
        $this->assertTrue($nota->expires_at->diffInHours(now()) >= 7);
        $this->assertTrue($nota->expires_at->diffInHours(now()) <= 8);
    }
}
