<?php

namespace MonteiroTI\SeiFilament\Tests;

use Livewire\Livewire;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Models\ConexaoSei;
use MonteiroTI\SeiFilament\Pages\CatalogosSei;
use MonteiroTI\SeiFilament\Support\CacheDosCatalogos;

class CatalogosTest extends TestCase
{
    public function test_exige_permissao(): void
    {
        $this->actingAs($this->usuario('Bia'));
        $this->get(CatalogosSei::getUrl())->assertForbidden();
    }

    public function test_mostra_as_unidades_da_conexao_padrao(): void
    {
        $this->actingAs($this->usuario('Ana', [CatalogosSei::PERMISSAO]));
        $fake = $this->seiFalso()->respond('listarUnidades', self::unidades());

        $pagina = Livewire::test(CatalogosSei::class)
            ->assertSet('conexao', 'default')
            ->assertSet('unidade', '110000001')
            ->assertSee('SEAD-PROT')
            ->assertSee('Protocolo Geral')
            ->assertSee('2 registro(s)')
            ->set('busca', 'protocolo geral')
            ->assertSee('1 registro(s)');

        $this->assertSame(['110000002'], array_column($pagina->instance()->resultado['linhas'], 'idUnidade'));

        $fake->assertCalled('listarUnidades');
    }

    public function test_troca_de_catalogo_e_de_unidade(): void
    {
        $this->actingAs($this->usuario('Ana', [CatalogosSei::PERMISSAO]));
        $fake = $this->seiFalso()
            ->respond('listarUnidades', self::unidades())
            ->respond('listarSeries', [(object) ['IdSerie' => '12', 'Nome' => 'Ofício', 'Aplicabilidade' => 'T']]);

        Livewire::test(CatalogosSei::class)
            ->set('unidade', '110000002')
            ->call('escolherCatalogo', 'series')
            ->assertSee('Ofício')
            ->assertSee('Todos');

        $fake->assertCalled('listarSeries', fn (array $args) => $args['IdUnidade'] === '110000002');
    }

    public function test_conexoes_do_banco_aparecem_no_seletor(): void
    {
        $this->actingAs($this->usuario('Ana', [CatalogosSei::PERMISSAO]));
        $this->seiFalso()->respond('listarUnidades', self::unidades());
        ConexaoSei::query()->create(['nome' => 'homologacao', 'wsdl' => 'https://x.dev', 'sigla_sistema' => 'S', 'identificacao_servico' => 'k', 'id_unidade' => '9']);

        Livewire::test(CatalogosSei::class)
            ->assertSee('default (padrão)')
            ->set('conexao', 'homologacao')
            ->assertSet('unidade', '9');
    }

    public function test_operacao_nao_liberada_e_erro_do_sei(): void
    {
        $this->actingAs($this->usuario('Ana', [CatalogosSei::PERMISSAO]));
        $this->seiFalso()->respond('listarUnidades', new ConnectionException('timeout', 'listarUnidades'));
        config()->set('sei.connections.default.operacoes', ['listarUnidades']);
        app(SeiManager::class)->purge();

        Livewire::test(CatalogosSei::class)
            ->assertSee('Não foi possível falar com o SEI')
            ->call('escolherCatalogo', 'usuarios')
            ->assertSee('listarUsuarios não está liberada');
    }

    public function test_limpar_cache_troca_o_prefixo(): void
    {
        $this->actingAs($this->usuario('Ana', [CatalogosSei::PERMISSAO]));
        $this->seiFalso()->respond('listarUnidades', self::unidades());

        $this->assertSame('sei:v1', config('sei.cache.prefix'));

        Livewire::test(CatalogosSei::class)
            ->callAction('limparCache')
            ->assertNotified('Cache dos catálogos limpo');

        $this->assertSame(2, CacheDosCatalogos::versao());
        $this->assertSame('sei:v2', config('sei.cache.prefix'));
    }
}
