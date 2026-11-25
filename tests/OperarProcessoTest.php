<?php

namespace MonteiroTI\SeiFilament\Tests;

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;
use MonteiroTi\Sei\Laravel\Testing\SeiFake;
use MonteiroTI\SeiFilament\Actions\ConcluirProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\EnviarProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\GerarProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\IncluirDocumentoSeiAction;
use MonteiroTI\SeiFilament\Actions\ReabrirProcessoSeiAction;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages\ViewProcessoSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\ProcessoSeiResource;
use MonteiroTI\SeiFilament\Tests\Fixtures\Processo;
use MonteiroTI\SeiFilament\Tests\Fixtures\ViewProcesso;

class OperarProcessoTest extends TestCase
{
    private function catalogos(): SeiFake
    {
        return $this->seiFalso()
            ->respond('listarTiposProcedimento', [(object) ['IdTipoProcedimento' => '100', 'Nome' => 'Férias']])
            ->respond('listarHipotesesLegais', [(object) ['IdHipoteseLegal' => '7', 'Nome' => 'Informação pessoal', 'NivelAcesso' => '1']])
            ->respond('listarSeries', [(object) ['IdSerie' => '12', 'Nome' => 'Requerimento', 'Aplicabilidade' => 'T']])
            ->respond('listarTiposConferencia', [])
            ->respond('listarUnidades', self::unidades());
    }

    private function vincular(Processo $registro, array $dados = []): SeiProcesso
    {
        return $registro->seiProcessos()->create([
            'conexao' => 'default',
            'id_procedimento' => '77',
            'protocolo_formatado' => '53000.012345/2026-11',
            ...$dados,
        ]);
    }

    public function test_gera_o_processo_com_os_documentos_e_o_vinculo(): void
    {
        $this->actingAs($this->usuario('Ana'));
        $fake = $this->catalogos()->respond('gerarProcedimento', (object) [
            'IdProcedimento' => '77',
            'ProcedimentoFormatado' => '53000.012345/2026-11',
            'LinkAcesso' => 'https://sei.teste.dev/p/77',
            'RetornoInclusaoDocumentos' => [(object) ['IdDocumento' => '900', 'DocumentoFormatado' => '0012345']],
        ]);
        $registro = Processo::query()->create();

        Livewire::test(ViewProcesso::class, ['record' => $registro->getRouteKey()])
            ->mountAction(GerarProcessoSeiAction::NOME)
            ->assertActionDataSet(['id_tipo_procedimento' => '100', 'especificacao' => "Processo #{$registro->getKey()}"])
            ->setActionData([
                'interessados' => [['nome' => 'Empresa X', 'documento' => '12.ABC.345/01DE-35'], ['nome' => 'João', 'documento' => '123.456.789-09']],
                'nivel_acesso' => 1,
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['id_hipotese_legal' => 'required'])
            ->setActionData(['id_hipotese_legal' => '7'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Processo 53000.012345/2026-11 gerado no SEI');

        $fake->assertCalled('gerarProcedimento', function (array $args) {
            $interessados = $args['Procedimento']['Interessados'];

            return $args['Procedimento']['IdTipoProcedimento'] === '100'
                && $args['Procedimento']['NivelAcesso'] === 1
                && $args['Procedimento']['IdHipoteseLegal'] === '7'
                && $interessados[0]['Cnpj'] === '12ABC34501DE35'
                && $interessados[1]['Cpf'] === '12345678909'
                && count($args['Documentos']) === 1;
        });

        $vinculo = $registro->seiProcesso()->sole();
        $this->assertSame('53000.012345/2026-11', $vinculo->protocolo_formatado);
        $this->assertSame('0012345', $vinculo->documentos()->sole()->documento_formatado);

        // Um processo por registro: a ação some.
        Livewire::test(ViewProcesso::class, ['record' => $registro->getRouteKey()])
            ->assertActionHidden(GerarProcessoSeiAction::NOME);
    }

    public function test_timeout_na_geracao_vira_operacao_incerta(): void
    {
        $this->actingAs($this->usuario('Ana'));
        $this->catalogos()->respond('gerarProcedimento', new ConnectionException('Operation timed out after 60000 milliseconds', 'gerarProcedimento', possivelmenteEnviada: true));
        $registro = Processo::query()->create();

        Livewire::test(ViewProcesso::class, ['record' => $registro->getRouteKey()])
            ->callAction(GerarProcessoSeiAction::NOME, ['id_tipo_procedimento' => '100', 'nivel_acesso' => 0])
            ->assertNotified('Processo não gerado');

        $this->assertSame(StatusOperacao::Incerta, SeiOperacao::query()->sole()->status);
        $this->assertFalse($registro->temProcessoSei());
    }

    public function test_inclui_documento_gerado(): void
    {
        $this->actingAs($this->usuario('Ana'));
        $fake = $this->catalogos()->respond('incluirDocumento', (object) ['IdDocumento' => '901', 'DocumentoFormatado' => '0012346', 'LinkAcesso' => 'https://sei.teste.dev/d/901']);
        $registro = Processo::query()->create();
        $this->vincular($registro);

        Livewire::test(ViewProcesso::class, ['record' => $registro->getRouteKey()])
            ->callAction(IncluirDocumentoSeiAction::NOME, ['id_serie' => '12', 'descricao' => 'Despacho', 'conteudo' => '<p>De acordo.</p>'])
            ->assertHasNoActionErrors()
            ->assertNotified('Documento 0012346 incluído no SEI');

        $fake->assertCalled('incluirDocumento', fn (array $args) => $args['Documento']['Tipo'] === 'G'
            && $args['Documento']['ProtocoloProcedimento'] === '53000.012345/2026-11'
            && base64_decode($args['Documento']['Conteudo']) === '<p>De acordo.</p>');
        $this->assertSame('0012346', SeiDocumento::query()->sole()->documento_formatado);
        $this->assertStringStartsWith('default:'.$registro->getMorphClass().':'.$registro->getKey().':incluirDocumento:', SeiOperacao::query()->sole()->chave);
    }

    public function test_inclui_documento_externo_com_arquivo(): void
    {
        $this->actingAs($this->usuario('Ana'));
        $fake = $this->catalogos()->respond('incluirDocumento', (object) ['IdDocumento' => '902', 'DocumentoFormatado' => '0012347']);
        $registro = Processo::query()->create();
        $this->vincular($registro);

        Livewire::test(ViewProcesso::class, ['record' => $registro->getRouteKey()])
            ->callAction(IncluirDocumentoSeiAction::NOME, [
                'tipo' => 'R',
                'id_serie' => '12',
                'arquivo' => UploadedFile::fake()->createWithContent('requerimento.pdf', '%PDF-1.4 conteúdo'),
                'data' => '2026-11-20',
                'remetente' => 'João da Silva',
            ])
            ->assertHasNoActionErrors();

        $fake->assertCalled('incluirDocumento', fn (array $args) => $args['Documento']['Tipo'] === 'R'
            && $args['Documento']['NomeArquivo'] === 'requerimento.pdf'
            && $args['Documento']['Data'] === '20/11/2026'
            && $args['Documento']['Remetente']['Nome'] === 'João da Silva'
            && base64_decode($args['Documento']['Conteudo']) === '%PDF-1.4 conteúdo');
    }

    public function test_envia_conclui_e_reabre(): void
    {
        $this->actingAs($this->usuario('Ana'));
        $fake = $this->catalogos()
            ->respond('enviarProcesso', true)
            ->respond('concluirProcesso', true)
            ->respond('reabrirProcesso', true)
            ->respond('consultarProcedimento', (object) ['IdProcedimento' => '77', 'ProcedimentoFormatado' => '53000.012345/2026-11', 'UnidadesProcedimentoAberto' => [(object) ['Unidade' => (object) ['IdUnidade' => '110000001', 'Sigla' => 'SEAD-GAB']]]]);
        $registro = Processo::query()->create();
        $vinculo = $this->vincular($registro, ['id_unidade_geradora' => '110000001']);

        Livewire::test(ViewProcesso::class, ['record' => $registro->getRouteKey()])
            ->assertActionHidden(ReabrirProcessoSeiAction::NOME)
            ->callAction(EnviarProcessoSeiAction::NOME, ['unidades' => ['110000002'], 'manter_aberto' => true, 'dias_retorno' => 5])
            ->assertHasNoActionErrors()
            ->assertNotified('Processo 53000.012345/2026-11 enviado')
            ->callAction(ConcluirProcessoSeiAction::NOME)
            ->assertNotified('Processo 53000.012345/2026-11 concluído');

        $fake->assertCalled('enviarProcesso', fn (array $args) => $args['UnidadesDestino'] === ['110000002']
            && $args['SinManterAbertoUnidade'] === 'S'
            && $args['DiasRetornoProgramado'] === 5
            && $args['IdUnidade'] === '110000001');
        $fake->assertCalled('concluirProcesso');

        $vinculo->forceFill(['aberto' => false])->save();

        Livewire::test(ViewProcesso::class, ['record' => $registro->getRouteKey()])
            ->assertActionHidden(EnviarProcessoSeiAction::NOME)
            ->callAction(ReabrirProcessoSeiAction::NOME)
            ->assertNotified('Processo 53000.012345/2026-11 reaberto');

        $fake->assertCalled('reabrirProcesso');
        $this->assertTrue($vinculo->refresh()->aberto);
    }

    public function test_operar_na_tela_do_processo_vinculado_exige_permissao(): void
    {
        $registro = Processo::query()->create();
        $vinculo = $this->vincular($registro);

        $this->actingAs($this->usuario('Bia', [ProcessoSeiResource::PERMISSAO]));
        Livewire::test(ViewProcessoSei::class, ['record' => $vinculo->getRouteKey()])
            ->assertActionHidden(EnviarProcessoSeiAction::NOME);

        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO, ProcessoSeiResource::PERMISSAO_OPERAR]));
        Livewire::test(ViewProcessoSei::class, ['record' => $vinculo->getRouteKey()])
            ->assertActionVisible(EnviarProcessoSeiAction::NOME);
    }
}
