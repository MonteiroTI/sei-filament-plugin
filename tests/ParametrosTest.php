<?php

namespace MonteiroTI\SeiFilament\Tests;

use Livewire\Livewire;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Laravel\Testing\SeiFake;
use MonteiroTI\SeiFilament\Models\ConexaoSei;
use MonteiroTI\SeiFilament\Pages\ParametrosSei;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\ConexaoSeiResource;

class ParametrosTest extends TestCase
{
    private function catalogos(): SeiFake
    {
        return $this->seiFalso()
            ->respond('listarSeries', [(object) ['IdSerie' => '12', 'Nome' => 'Ofício', 'Aplicabilidade' => 'T'], (object) ['IdSerie' => '13', 'Nome' => 'Despacho', 'Aplicabilidade' => 'T']])
            ->respond('listarUnidades', self::unidades());
    }

    public function test_exige_a_permissao_da_configuracao(): void
    {
        $this->actingAs($this->usuario('Bia'));
        $this->get(ParametrosSei::getUrl())->assertForbidden();
    }

    public function test_confere_os_parametros_do_config(): void
    {
        $this->actingAs($this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]));
        $this->catalogos();
        config()->set('sei.connections.default.parametros', ['series' => ['oficio' => '12', 'portaria' => '99'], 'outros' => ['x' => '1']]);

        $pagina = Livewire::test(ParametrosSei::class)
            ->assertSee('series.oficio')
            ->assertSee('Ofício')
            ->assertSee('Não encontrado ou não liberado')
            ->assertSee('Grupo sem catálogo')
            ->assertSee('Esta conexão vem do config/sei.php')
            ->assertActionHidden('editar');

        $this->assertSame(['ok', 'nao_encontrado', 'sem_catalogo'], array_column($pagina->instance()->conferencia['linhas'], 'situacao'));
    }

    public function test_edita_os_parametros_no_banco(): void
    {
        $this->actingAs($this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]));
        $this->catalogos();
        ConexaoSei::query()->create(['nome' => 'default', 'wsdl' => 'https://x.dev', 'sigla_sistema' => 'S', 'identificacao_servico' => 'k']);

        Livewire::test(ParametrosSei::class)
            ->assertActionVisible('editar')
            ->callAction('editar', ['series' => [['chave' => 'despacho', 'id' => '13']], 'unidades' => [['chave' => 'protocolo', 'id' => '110000002']]])
            ->assertHasNoActionErrors()
            ->assertNotified('Parâmetros salvos')
            ->assertSee('series.despacho');

        $manager = app(SeiManager::class);
        $this->assertSame('13', $manager->parametro('series.despacho'));
        $this->assertSame('110000002', $manager->parametro('unidades.protocolo'));
        // O do config continua valendo.
        $this->assertSame('12', $manager->parametro('series.oficio'));
    }

    public function test_chave_invalida(): void
    {
        $this->actingAs($this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]));
        $this->catalogos();
        ConexaoSei::query()->create(['nome' => 'default', 'wsdl' => 'https://x.dev', 'sigla_sistema' => 'S', 'identificacao_servico' => 'k']);

        Livewire::test(ParametrosSei::class)
            ->callAction('editar', ['series' => [['chave' => 'Ofício Geral', 'id' => '12']]])
            ->assertHasActionErrors(['series.0.chave' => 'regex']);
    }
}
