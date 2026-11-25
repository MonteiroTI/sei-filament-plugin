<?php

namespace MonteiroTI\SeiFilament\Tests;

use Livewire\Livewire;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTI\SeiFilament\Actions\IncluirEmBlocoSeiAction;
use MonteiroTI\SeiFilament\Pages\BlocosSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages\ViewProcessoSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\ProcessoSeiResource;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\RelationManagers\DocumentosRelationManager;

class BlocosTest extends TestCase
{
    private static function bloco(string $estado = 'A'): object
    {
        return (object) [
            'IdBloco' => '4321', 'Descricao' => 'Assinar portarias', 'Tipo' => 'A', 'Estado' => $estado,
            'Unidade' => (object) ['IdUnidade' => '110000001', 'Sigla' => 'SEAD-GAB'],
            'Protocolos' => [(object) ['ProtocoloFormatado' => '0012345', 'Identificacao' => 'Portaria 12', 'Assinaturas' => (object) ['Nome' => 'Maria Souza', 'CargoFuncao' => 'Diretora']]],
        ];
    }

    /** @return array{0: SeiProcesso, 1: SeiDocumento, 2: SeiDocumento} */
    private function documentos(): array
    {
        $processo = SeiProcesso::query()->create(['conexao' => 'default', 'id_procedimento' => '77', 'protocolo_formatado' => '53000.012345/2026-11']);
        $a = SeiDocumento::query()->create(['sei_processo_id' => $processo->id, 'conexao' => 'default', 'id_documento' => '1', 'documento_formatado' => '0012345']);
        $b = SeiDocumento::query()->create(['sei_processo_id' => $processo->id, 'conexao' => 'default', 'id_documento' => '2', 'documento_formatado' => '0012346']);

        return [$processo, $a, $b];
    }

    public function test_pagina_exige_permissao(): void
    {
        $this->actingAs($this->usuario('Bia'));
        $this->get(BlocosSei::getUrl())->assertForbidden();
    }

    public function test_consulta_o_bloco_e_disponibiliza(): void
    {
        $this->actingAs($this->usuario('Ana', [BlocosSei::PERMISSAO]));
        $fake = $this->seiFalso()
            ->respond('listarUnidades', self::unidades())
            ->respond('consultarBloco', self::bloco('A'), self::bloco('A'), self::bloco('A'), self::bloco('D'))
            ->respond('disponibilizarBloco', true)
            ->respond('retirarDocumentoBloco', true);

        Livewire::withQueryParams(['bloco' => '4321'])
            ->test(BlocosSei::class)
            ->assertSee('Assinar portarias')
            ->assertSee('Portaria 12')
            ->assertSee('Maria Souza')
            ->assertActionVisible('disponibilizar')
            ->assertActionHidden('reabrir')
            ->callAction('disponibilizar')
            ->assertNotified('Bloco: Disponibilizar feito')
            ->assertSee('Disponibilizado')
            ->call('retirar', '0012345')
            ->assertNotified('0012345 retirado do bloco');

        $fake->assertCalled('disponibilizarBloco', fn (array $args) => $args['IdBloco'] === '4321' && $args['IdUnidade'] === '110000001');
        $fake->assertCalled('retirarDocumentoBloco', fn (array $args) => $args['ProtocoloDocumento'] === '0012345');
    }

    public function test_bloco_que_nao_e_da_unidade(): void
    {
        $this->actingAs($this->usuario('Ana', [BlocosSei::PERMISSAO]));
        $this->seiFalso()
            ->respond('listarUnidades', self::unidades())
            ->respond('consultarBloco', new FaultException('Bloco [9] não encontrado.', 'consultarBloco'));

        Livewire::test(BlocosSei::class)
            ->set('bloco', '9')
            ->call('consultar')
            ->assertSee('Bloco [9] não encontrado.')
            ->assertActionHidden('excluir');
    }

    public function test_inclui_documentos_num_bloco_novo_em_massa(): void
    {
        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO, ProcessoSeiResource::PERMISSAO_OPERAR, BlocosSei::PERMISSAO]));
        $fake = $this->seiFalso()->respond('listarUnidades', self::unidades())->respond('gerarBloco', '4321');
        [$processo, $a, $b] = $this->documentos();

        Livewire::test(DocumentosRelationManager::class, ['ownerRecord' => $processo, 'pageClass' => ViewProcessoSei::class])
            ->callTableBulkAction(IncluirEmBlocoSeiAction::NOME, [$a, $b], ['bloco' => 'novo', 'descricao' => 'Assinar', 'unidades' => ['110000002'], 'disponibilizar' => true])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified('2 documentos incluídos no bloco 4321');

        $fake->assertCalled('gerarBloco', fn (array $args) => $args['Tipo'] === 'A'
            && $args['Documentos'] === ['0012345', '0012346']
            && $args['UnidadesDisponibilizacao'] === ['110000002']
            && $args['SinDisponibilizar'] === 'S');
    }

    public function test_inclui_documento_num_bloco_existente(): void
    {
        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO, ProcessoSeiResource::PERMISSAO_OPERAR]));
        $fake = $this->seiFalso()->respond('listarUnidades', self::unidades())->respond('incluirDocumentoBloco', true);
        [$processo, $a] = $this->documentos();

        Livewire::test(DocumentosRelationManager::class, ['ownerRecord' => $processo, 'pageClass' => ViewProcessoSei::class])
            ->callTableAction(IncluirEmBlocoSeiAction::NOME, $a, ['bloco' => 'existente', 'id_bloco' => '4321', 'anotacao' => 'Urgente'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Documento incluído no bloco 4321');

        $fake->assertCalled('incluirDocumentoBloco', fn (array $args) => $args['IdBloco'] === '4321' && $args['ProtocoloDocumento'] === '0012345' && $args['Anotacao'] === 'Urgente');
    }
}
