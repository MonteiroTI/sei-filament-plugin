<?php

namespace MonteiroTI\SeiFilament\Tests;

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTI\SeiFilament\Actions\AgendarPublicacaoSeiAction;
use MonteiroTI\SeiFilament\Actions\CancelarPublicacaoSeiAction;
use MonteiroTI\SeiFilament\Actions\RegistrarOuvidoriaSeiAction;
use MonteiroTI\SeiFilament\Pages\ContatosSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages\ViewProcessoSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\ProcessoSeiResource;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\RelationManagers\DocumentosRelationManager;
use MonteiroTI\SeiFilament\Tests\Fixtures\ListProcessos;

class ContatosPublicacoesOuvidoriaTest extends TestCase
{
    private static function contato(array $dados = []): object
    {
        return (object) [
            'IdContato' => '501', 'Nome' => 'Maria Souza', 'Sigla' => 'msouza', 'NomeTipoContato' => 'Pessoa física',
            'Cpf' => '12345678909', 'Email' => 'maria@antes.dev', 'Endereco' => 'Rua A, 1', 'Matricula' => '777', 'SinAtivo' => 'S',
            ...$dados,
        ];
    }

    public function test_contatos_exigem_permissao(): void
    {
        $this->actingAs($this->usuario('Bia'));
        $this->get(ContatosSei::getUrl())->assertForbidden();
    }

    public function test_busca_e_edita_contato_sem_perder_os_outros_campos(): void
    {
        $this->actingAs($this->usuario('Ana', [ContatosSei::PERMISSAO]));
        $fake = $this->seiFalso()
            ->respond('listarUnidades', self::unidades())
            ->respond('listarContatos', [self::contato()])
            ->respond('atualizarContatos', true);

        Livewire::test(ContatosSei::class)
            ->set('filtro.cnpj', '12.ABC.345/01DE-35')
            ->call('buscar')
            ->assertSee('Maria Souza')
            ->assertSee('Ativo')
            ->assertDontSee('Próxima')
            ->callAction('editar', ['email' => 'maria@depois.dev', 'nome' => 'Maria Souza'], ['id' => '501'])
            ->assertHasNoActionErrors()
            ->assertNotified('Contato atualizado');

        $fake->assertCalled('listarContatos', fn (array $args) => $args['Cnpj'] === '12ABC34501DE35' && $args['PaginaRegistros'] === ContatosSei::POR_PAGINA);
        $fake->assertCalled('atualizarContatos', fn (array $args) => $args['Contatos'][0]['StaOperacao'] === 'A'
            && $args['Contatos'][0]['Email'] === 'maria@depois.dev'
            // O que a tela não edita vai como o SEI devolveu.
            && $args['Contatos'][0]['Matricula'] === '777'
            && $args['Contatos'][0]['Cpf'] === '12345678909');
    }

    public function test_desativa_contato(): void
    {
        $this->actingAs($this->usuario('Ana', [ContatosSei::PERMISSAO]));
        $fake = $this->seiFalso()
            ->respond('listarUnidades', self::unidades())
            ->respond('listarContatos', [self::contato()])
            ->respond('atualizarContatos', true);

        Livewire::test(ContatosSei::class)
            ->call('buscar')
            ->callAction('desativar', [], ['id' => '501'])
            ->assertNotified('Contato desativado');

        $fake->assertCalled('atualizarContatos', fn (array $args) => $args['Contatos'][0]['StaOperacao'] === 'D' && $args['Contatos'][0]['IdContato'] === '501');
    }

    public function test_agenda_e_cancela_a_publicacao_do_documento(): void
    {
        $this->actingAs($this->usuario('Ana', [ProcessoSeiResource::PERMISSAO, ProcessoSeiResource::PERMISSAO_OPERAR]));
        $fake = $this->seiFalso()->respond('agendarPublicacao', '88')->respond('cancelarAgendamentoPublicacao', true);
        $processo = SeiProcesso::query()->create(['conexao' => 'default', 'id_procedimento' => '77', 'protocolo_formatado' => '53000.012345/2026-11']);
        $documento = SeiDocumento::query()->create(['sei_processo_id' => $processo->id, 'conexao' => 'default', 'id_documento' => '1', 'documento_formatado' => '0012345']);

        Livewire::test(DocumentosRelationManager::class, ['ownerRecord' => $processo, 'pageClass' => ViewProcessoSei::class])
            ->assertTableActionHidden(CancelarPublicacaoSeiAction::NOME, $documento)
            ->callTableAction(AgendarPublicacaoSeiAction::NOME, $documento, ['motivo' => 1, 'id_veiculo' => '3', 'data' => now()->addDays(2)->toDateString(), 'resumo' => 'Portaria 12'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Publicação do documento 0012345 agendada');

        $fake->assertCalled('agendarPublicacao', fn (array $args) => $args['ProtocoloDocumento'] === '0012345'
            && $args['IdVeiculoPublicacao'] === '3'
            && $args['StaMotivo'] === 1
            && $args['Resumo'] === 'Portaria 12');
        $this->assertSame('A', $documento->refresh()->publicacao_estado);

        Livewire::test(DocumentosRelationManager::class, ['ownerRecord' => $processo, 'pageClass' => ViewProcessoSei::class])
            ->assertTableActionHidden(AgendarPublicacaoSeiAction::NOME, $documento)
            ->callTableAction(CancelarPublicacaoSeiAction::NOME, $documento)
            ->assertNotified('Publicação do documento 0012345 cancelada');

        $this->assertNull($documento->refresh()->publicacao_estado);
    }

    public function test_registra_manifestacao_de_ouvidoria(): void
    {
        $this->actingAs($this->usuario('Ana'));
        $fake = $this->seiFalso()
            ->respond('listarTiposProcedimentoOuvidoria', [(object) ['IdTipoProcedimento' => '300', 'Nome' => 'Ouvidoria: Reclamação']])
            ->respond('registrarOuvidoria', (object) ['IdProcedimento' => '90', 'ProcedimentoFormatado' => '53000.000090/2026-11']);

        Livewire::test(ListProcessos::class)
            ->callAction(RegistrarOuvidoriaSeiAction::NOME, ['id_tipo_procedimento' => '300', 'nome' => 'João', 'email' => 'joao@x.dev', 'mensagem' => 'Atendimento demorado.'])
            ->assertHasActionErrors(['cpf' => 'required'])
            ->callAction(RegistrarOuvidoriaSeiAction::NOME, [
                'id_tipo_procedimento' => '300', 'nome' => 'João', 'email' => 'joao@x.dev', 'cpf' => '123.456.789-09',
                'mensagem' => 'Atendimento demorado.', 'anexos' => [UploadedFile::fake()->createWithContent('foto.jpg', 'JPEG')],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Manifestação registrada: processo 53000.000090/2026-11');

        $fake->assertCalled('registrarOuvidoria', fn (array $args) => $args['IdOrgao'] === '0'
            && $args['IdTipoProcedimento'] === '300'
            && $args['Cpf'] === '12345678909'
            && $args['SinAnonimo'] === 'N'
            && $args['Anexos'][0]['Nome'] === 'foto.jpg');
    }
}
