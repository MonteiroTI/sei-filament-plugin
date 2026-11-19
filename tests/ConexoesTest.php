<?php

namespace MonteiroTI\SeiFilament\Tests;

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use MonteiroTi\Sei\Core\Exceptions\AccessDeniedException;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Models\ConexaoSei;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\ConexaoSeiResource;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\Pages\CreateConexaoSei;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\Pages\EditConexaoSei;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\Pages\ListConexoesSei;

class ConexoesTest extends TestCase
{
    private function conexao(array $dados = []): ConexaoSei
    {
        return ConexaoSei::query()->create([
            'nome' => 'homologacao',
            'wsdl' => 'https://seihom.teste.dev/sei/controlador_ws.php?servico=sei',
            'sigla_sistema' => 'SISTEMA_HOM',
            'identificacao_servico' => 'chave-do-banco',
            'id_unidade' => '110000002',
            ...$dados,
        ]);
    }

    public function test_conexao_do_banco_entra_no_config(): void
    {
        $this->conexao();
        $this->conexao(['nome' => 'default', 'identificacao_servico' => 'chave-nova', 'padrao' => true]);

        $manager = app(SeiManager::class);

        $this->assertSame('chave-do-banco', $manager->connectionConfig('homologacao')['identificacao_servico']);
        $this->assertSame('chave-nova', $manager->connectionConfig('default')['identificacao_servico']);
        // O que o banco não tem continua vindo do config/sei.php.
        $this->assertSame('12', $manager->parametro('series.oficio'));
        $this->assertSame('default', $manager->getDefaultConnection());
    }

    public function test_conexao_padrao_e_desativada(): void
    {
        $conexao = $this->conexao(['padrao' => true]);
        $manager = app(SeiManager::class);
        $this->assertSame('homologacao', $manager->getDefaultConnection());

        $conexao->update(['ativo' => false]);

        $this->assertSame('default', $manager->getDefaultConnection());
        $this->expectException(\InvalidArgumentException::class);
        $manager->connectionConfig('homologacao');
    }

    public function test_so_uma_conexao_padrao(): void
    {
        $a = $this->conexao(['padrao' => true]);
        $b = $this->conexao(['nome' => 'producao', 'padrao' => true]);

        $this->assertFalse($a->fresh()->padrao);
        $this->assertTrue($b->fresh()->padrao);
    }

    public function test_chaves_ficam_criptografadas_no_banco(): void
    {
        $this->conexao(['sip_wsdl' => 'https://sip.teste.dev/x', 'sip_chave_acesso' => 'chave-sip']);

        $linha = DB::table('sei_conexoes')->first();

        $this->assertNotSame('chave-do-banco', $linha->identificacao_servico);
        $this->assertStringNotContainsString('chave-sip', $linha->sip_chave_acesso);
        $this->assertSame('chave-sip', app(SeiManager::class)->sipConnectionConfig('homologacao')['chave_acesso']);
        $this->assertArrayNotHasKey('identificacao_servico', ConexaoSei::query()->first()->toArray());
    }

    public function test_tela_exige_permissao(): void
    {
        $this->actingAs($this->usuario('Bia'));
        $this->get(ConexaoSeiResource::getUrl())->assertForbidden();

        $this->actingAs($this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]));
        $this->get(ConexaoSeiResource::getUrl())->assertOk()->assertSee('Nenhuma conexão no banco');
    }

    public function test_cadastra_pela_tela(): void
    {
        $this->actingAs($this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]));

        Livewire::test(CreateConexaoSei::class)
            ->fillForm([
                'nome' => 'producao',
                'wsdl' => 'https://sei.teste.dev/sei/controlador_ws.php?servico=sei',
                'sigla_sistema' => 'SISTEMA',
                'identificacao_servico' => 'segredo',
                'operacoes' => ['consultar*', 'gerarProcedimento'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $conexao = ConexaoSei::query()->where('nome', 'producao')->firstOrFail();
        $this->assertSame('segredo', $conexao->identificacao_servico);
        $this->assertSame(['consultar*', 'gerarProcedimento'], app(SeiManager::class)->connectionConfig('producao')['operacoes']);
    }

    public function test_chave_em_branco_na_edicao_mantem_a_atual(): void
    {
        $this->actingAs($this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]));
        $conexao = $this->conexao();

        Livewire::test(EditConexaoSei::class, ['record' => $conexao->getRouteKey()])
            ->assertFormSet(['identificacao_servico' => null])
            ->fillForm(['sigla_sistema' => 'OUTRO', 'identificacao_servico' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $conexao->refresh();
        $this->assertSame('OUTRO', $conexao->sigla_sistema);
        $this->assertSame('chave-do-banco', $conexao->identificacao_servico);
    }

    public function test_testar_conexao_ok(): void
    {
        $this->actingAs($this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]));
        $fake = $this->seiFalso()->respond('listarUnidades', self::unidades());
        $conexao = $this->conexao();

        Livewire::test(ListConexoesSei::class)
            ->callTableAction('testar', $conexao)
            ->assertNotified('Conexão "homologacao" ok');

        $fake->assertCalled('listarUnidades', fn (array $args) => $args['SiglaSistema'] === 'SISTEMA_HOM' && $args['IdentificacaoServico'] === 'chave-do-banco');
        $conexao->refresh();
        $this->assertTrue($conexao->teste_ok);
        $this->assertNotNull($conexao->testada_em);
        $this->assertStringContainsString('2 unidade(s)', $conexao->resultado_teste);
    }

    public function test_testar_conexao_com_falha(): void
    {
        $this->actingAs($this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]));
        $this->seiFalso()->respond('listarUnidades', new AccessDeniedException('Acesso negado. Chave de acesso inválida.', 'listarUnidades'));
        $conexao = $this->conexao();

        Livewire::test(EditConexaoSei::class, ['record' => $conexao->getRouteKey()])
            ->callAction('testar')
            ->assertNotified('Conexão "homologacao" falhou');

        $conexao->refresh();
        $this->assertFalse($conexao->teste_ok);
        $this->assertStringContainsString('Chave de acesso inválida', $conexao->resultado_teste);
    }

    public function test_testar_sem_listar_unidades_liberada(): void
    {
        $this->actingAs($this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]));
        $fake = $this->seiFalso();
        $conexao = $this->conexao(['operacoes' => ['gerarProcedimento']]);

        Livewire::test(ListConexoesSei::class)->callTableAction('testar', $conexao);

        $fake->assertNothingCalled();
        $this->assertStringContainsString('credenciais não verificadas', $conexao->refresh()->resultado_teste);
    }
}
