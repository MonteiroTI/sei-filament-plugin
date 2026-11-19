<?php

namespace MonteiroTI\SeiFilament\Tests;

use Livewire\Livewire;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTI\SeiFilament\Tests\Fixtures\FormularioSei;

class SelectsTest extends TestCase
{
    public function test_opcoes_vem_do_catalogo(): void
    {
        $fake = $this->seiFalso()
            ->respond('listarUnidades', self::unidades())
            ->respond('listarSeries', [
                (object) ['IdSerie' => '12', 'Nome' => 'Ofício', 'Aplicabilidade' => 'T'],
                (object) ['IdSerie' => '13', 'Nome' => 'Despacho', 'Aplicabilidade' => 'I'],
            ]);

        Livewire::test(FormularioSei::class)
            ->assertSee('SEAD-PROT')
            ->assertSee('Protocolo Geral')
            ->assertSee('Despacho')
            ->assertSee('Tipos de documento');

        $fake->assertCalled('listarSeries', fn (array $args) => $args['IdUnidade'] === '110000002');
    }

    public function test_sei_fora_mostra_hint_e_o_formulario_abre(): void
    {
        $this->seiFalso()
            ->respond('listarUnidades', new ConnectionException('Could not connect to host', 'listarUnidades'))
            ->respond('listarSeries', []);

        Livewire::test(FormularioSei::class)
            ->assertOk()
            ->assertSee('SEI indisponível: Não foi possível falar com o SEI.');
    }
}
