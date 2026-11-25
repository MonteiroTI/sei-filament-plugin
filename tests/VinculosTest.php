<?php

namespace MonteiroTI\SeiFilament\Tests;

use Filament\Facades\Filament;
use Livewire\Livewire;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;
use MonteiroTI\SeiFilament\Resources\OperacoesSei\OperacaoSeiResource;
use MonteiroTI\SeiFilament\Resources\OperacoesSei\Pages\ListOperacoesSei;
use MonteiroTI\SeiFilament\Resources\OperacoesSei\Pages\ViewOperacaoSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages\ListProcessosSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages\ViewProcessoSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\ProcessoSeiResource;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\RelationManagers\DocumentosRelationManager;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Tests\Fixtures\Processo;

class VinculosTest extends TestCase
{
    private function processoSei(array $dados = []): SeiProcesso
    {
        return SeiProcesso::query()->create([
            'conexao' => 'default',
            'id_procedimento' => '77',
            'protocolo_formatado' => '53000.012345/2026-11',
            'link_acesso' => 'https://sei.teste.dev/sei/controlador.php?id=77',
            'nivel_acesso' => 1,
            'unidades_abertas' => [['id' => '2', 'sigla' => 'SEAD-RH', 'usuario' => null]],
            ...$dados,
        ]);
    }

    private function operacao(array $dados = []): SeiOperacao
    {
        return SeiOperacao::query()->create([
            'chave' => 'ferias:42:gerar',
            'conexao' => 'default',
            'operacao' => 'gerarProcedimento',
            'status' => StatusOperacao::Incerta,
            'tentativas' => 1,
            'erro' => 'Timeout depois do envio.',
            ...$dados,
        ]);
    }

    public function test_telas_so_com_vinculos_ligado(): void
    {
        $this->assertContains(ProcessoSeiResource::class, Filament::getPanel('admin')->getResources());
        $this->assertContains(OperacaoSeiResource::class, Filament::getPanel('admin')->getResources());

        $this->assertFalse(SeiFilamentPlugin::make()->temVinculos());
    }

    public function test_processos_exigem_permissao(): void
    {
        $this->actingAs($this->usuario('Bia'));
        $this->get(ProcessoSeiResource::getUrl())->assertForbidden();
        $this->get(OperacaoSeiResource::getUrl())->assertForbidden();
    }

    public function test_lista_e_mostra_o_processo_com_os_documentos(): void
    {
        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO]));
        $registro = Processo::query()->create(['protocolo_sei' => null]);
        $processo = $this->processoSei(['vinculavel_type' => Processo::class, 'vinculavel_id' => $registro->getKey()]);
        SeiDocumento::query()->create([
            'sei_processo_id' => $processo->getKey(), 'conexao' => 'default', 'id_documento' => '900',
            'documento_formatado' => '0012345', 'tipo' => 'G', 'id_serie' => '12',
            'assinaturas' => [['nome' => 'Maria', 'sigla' => 'maria', 'cargo' => 'Diretora', 'data_hora' => null]],
            'assinado_em' => now(), 'publicacao_estado' => 'P',
        ]);

        Livewire::test(ListProcessosSei::class)
            ->assertCanSeeTableRecords([$processo])
            ->assertSee('Processo #'.$registro->getKey())
            ->assertSee('Restrito')
            ->assertSee('SEAD-RH');

        Livewire::test(ViewProcessoSei::class, ['record' => $processo->getRouteKey()])
            ->assertSee('Processo 53000.012345/2026-11')
            ->assertSee('Abrir no SEI');

        Livewire::test(DocumentosRelationManager::class, ['ownerRecord' => $processo, 'pageClass' => ViewProcessoSei::class])
            ->assertSee('0012345')
            ->assertSee('Gerado')
            ->assertSee('Publicado');
    }

    public function test_sincronizar_agora(): void
    {
        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO]));
        $processo = $this->processoSei(['sincronizar' => false, 'erro_sincronizacao' => 'erro antigo']);
        $fake = $this->seiFalso()->respond('consultarProcedimento', (object) [
            'IdProcedimento' => '77',
            'ProcedimentoFormatado' => '53000.012345/2026-11',
            'UnidadesProcedimentoAberto' => [],
            'AndamentoConclusao' => (object) ['IdAndamento' => '9', 'DataHora' => '20/11/2026 10:00:00'],
            'UltimoAndamento' => (object) ['IdAndamento' => '9', 'DataHora' => '20/11/2026 10:00:00'],
        ]);

        Livewire::test(ListProcessosSei::class)
            ->callTableAction('sincronizar', $processo)
            ->assertNotified('Processo 53000.012345/2026-11 sincronizado');

        $fake->assertCalled('consultarProcedimento');
        $processo->refresh();
        $this->assertTrue($processo->sincronizar);
        $this->assertNull($processo->erro_sincronizacao);
        $this->assertNotNull($processo->sincronizado_em);
    }

    public function test_sincronizar_com_recusa_do_sei(): void
    {
        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO]));
        $processo = $this->processoSei();
        $this->seiFalso()->respond('consultarProcedimento', new FaultException('Processo não encontrado.', 'consultarProcedimento'));

        Livewire::test(ViewProcessoSei::class, ['record' => $processo->getRouteKey()])
            ->callAction('sincronizar')
            ->assertNotified('O SEI recusou a consulta');

        $this->assertStringContainsString('Processo não encontrado', $processo->refresh()->erro_sincronizacao);
    }

    public function test_operacoes_incertas_no_menu_e_na_aba(): void
    {
        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO]));
        $incerta = $this->operacao();
        $concluida = $this->operacao(['chave' => 'ferias:41:gerar', 'status' => StatusOperacao::Concluida, 'erro' => null]);

        $this->assertSame('1', OperacaoSeiResource::getNavigationBadge());

        Livewire::test(ListOperacoesSei::class)
            ->assertCanSeeTableRecords([$incerta])
            ->assertCanNotSeeTableRecords([$concluida])
            ->set('activeTab', 'todas')
            ->assertCanSeeTableRecords([$incerta, $concluida])
            // Sem a permissão de resolver, só vê.
            ->assertTableActionHidden('resolver', $incerta);
    }

    public function test_resolver_descartando(): void
    {
        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO, OperacaoSeiResource::PERMISSAO_RESOLVER]));
        $operacao = $this->operacao();

        Livewire::test(ListOperacoesSei::class)
            ->callTableAction('resolver', $operacao, ['como' => 'descartar'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Operação marcada como Descartada');

        $this->assertSame(StatusOperacao::Descartada, $operacao->refresh()->status);
        $this->assertNull(OperacaoSeiResource::getNavigationBadge());
    }

    public function test_resolver_informando_o_processo_cria_o_vinculo(): void
    {
        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO, OperacaoSeiResource::PERMISSAO_RESOLVER]));
        $registro = Processo::query()->create();
        $operacao = $this->operacao(['vinculavel_type' => Processo::class, 'vinculavel_id' => $registro->getKey()]);
        $this->seiFalso()->respond('consultarProcedimento', (object) [
            'IdProcedimento' => '88',
            'ProcedimentoFormatado' => '53000.099999/2026-11',
            'LinkAcesso' => 'https://sei.teste.dev/x',
            'TipoProcedimento' => (object) ['IdTipoProcedimento' => '100', 'Nome' => 'Férias'],
        ]);

        Livewire::test(ViewOperacaoSei::class, ['record' => $operacao->getRouteKey()])
            ->callAction('resolver', ['como' => 'processo'])
            ->assertHasActionErrors(['protocolo' => 'required'])
            ->callAction('resolver', ['como' => 'processo', 'protocolo' => '53000.099999/2026-11'])
            ->assertHasNoActionErrors();

        $this->assertSame(StatusOperacao::Concluida, $operacao->refresh()->status);
        $vinculo = SeiProcesso::query()->where('protocolo_formatado', '53000.099999/2026-11')->sole();
        $this->assertSame(Processo::class, $vinculo->vinculavel_type);
        $this->assertSame('100', $vinculo->id_tipo_procedimento);
    }

    public function test_resolver_com_erro_do_sei_mantem_a_operacao_incerta(): void
    {
        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO, OperacaoSeiResource::PERMISSAO_RESOLVER]));
        $operacao = $this->operacao();
        $this->seiFalso()->respond('consultarProcedimento', new FaultException('Processo não encontrado.', 'consultarProcedimento'));

        Livewire::test(ListOperacoesSei::class)
            ->callTableAction('resolver', $operacao, ['como' => 'processo', 'protocolo' => '999'])
            ->assertNotified('Não foi possível resolver');

        $this->assertSame(StatusOperacao::Incerta, $operacao->refresh()->status);
    }
}
