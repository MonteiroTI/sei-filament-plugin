# SEI no Filament

Plugin do Filament 3 para o [conector do SEI](https://github.com/MonteiroTI/sei-integration-php) (`monteiroti/sei-integration`).

| Tela | O que faz | Permissão padrão |
|---|---|---|
| **Conexões** | Cadastro das conexões do SEI (e do SIP) no banco, com a chave criptografada, e o botão **Testar** (o mesmo do `sei:ping`) | `editar_configuracao:sei` |
| **Catálogos** | Unidades, tipos de documento, tipos de processo, hipóteses legais, marcadores, usuários... por conexão e unidade, com busca e **Limpar cache** | `consultar_catalogos:sei` |
| **Log das chamadas** | Cada chamada ao SEI/SIP, com tempo, erro e argumentos (mascarados pelo conector) | `ver_log:sei` |
| **Widget** | No dashboard: último teste da conexão padrão, falhas e tempo médio nas últimas 24 h | quem configura ou vê o log |
| **Processos vinculados** (com `->vinculos()`) | Processos e documentos que o sistema gerou ou acompanha, com **Consultar no SEI** e **Sincronizar** | `ver_vinculos:sei` |
| **Operações** (com `->vinculos()`) | Operações com idempotência; as incertas aparecem no menu e são resolvidas na tela | `ver_vinculos:sei`; resolver: `resolver_operacoes:sei` |
| **Parâmetros** | IDs do SEI usados pelo sistema (`Sei::parametro()`), conferidos contra os catálogos e editáveis no banco | `editar_configuracao:sei` |
| **Blocos** | Bloco de assinatura pelo número: documentos com as assinaturas, disponibilizar, concluir, retirar documento... | `gerenciar_blocos:sei` |
| **SIP** (com `->sip()`) | Permissões do sistema no SIP: buscar, conceder, retirar; e replicar usuário | `administrar_sip:sei` |
| **Contatos** (com `->contatos()`) | Contatos do SEI: buscar, editar, desativar, reativar | `gerenciar_contatos:sei` |
| **Avisos no sino** (com `->avisos()`) | Processo tramitado, concluído, reaberto, documento assinado ou publicado, sincronização parada | quem tem `receber_avisos:sei` |

| Peça para as telas do sistema | Uso |
|---|---|
| `ConsultarProcessoSeiAction` | Slide-over com o processo no SEI: tipo, nível de acesso, onde está aberto, interessados, link e andamentos |
| `GerarProcessoSeiAction` | Gera o processo do registro (uma vez só), com interessados, nível de acesso e documentos do sistema |
| `IncluirDocumentoSeiAction` | Inclui documento gerado (HTML) ou externo (arquivo) no processo do registro |
| `EnviarProcessoSeiAction`, `ConcluirProcessoSeiAction`, `ReabrirProcessoSeiAction` | Tramitação do processo do registro |
| `IncluirEmBlocoSeiAction` | Põe documentos num bloco de assinatura novo ou existente (também em massa) |
| `AgendarPublicacaoSeiAction`, `CancelarPublicacaoSeiAction` | Publicação do documento no veículo do órgão |
| `RegistrarOuvidoriaSeiAction` | Registra manifestação de ouvidoria (o SEI gera o processo) |
| `ReplicarUsuarioSipAction` | Cadastra, atualiza, desativa ou exclui o usuário do sistema no SIP |
| `UnidadeSeiSelect`, `SerieSeiSelect`, `TipoProcessoSeiSelect`, `HipoteseLegalSeiSelect`, `MarcadorSeiSelect`, `UsuarioSeiSelect`, `TipoConferenciaSeiSelect`, `TipoPrioridadeSeiSelect` | Selects com as opções do catálogo do SEI |
| `Support\Rotulos` | Rótulo e cor em pt-BR dos enums do conector (`NivelAcesso`, `EstadoBloco`...) |
| `Support\Erros` | Exceções do conector em notificação do Filament |

## Versões

| Plugin | Filament | Laravel | Conector |
|---|---|---|---|
| 1.x (`main`) | 3.3 | 12 | `^0.1` |

## Instalação

Os dois repositórios são privados: o `composer.json` do sistema precisa dos dois e de um token do GitHub com acesso a eles (`composer config --global github-oauth.github.com <token>`).

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/MonteiroTI/sei-filament-plugin" },
    { "type": "vcs", "url": "https://github.com/MonteiroTI/sei-integration-php" }
]
```

```bash
composer require monteiroti/sei-filament-plugin:^1.0
php artisan vendor:publish --tag=sei-filament-migrations
php artisan migrate
```

No painel:

```php
use MonteiroTI\SeiFilament\SeiFilamentPlugin;

$panel->plugin(SeiFilamentPlugin::make());
```

## Opções

```php
SeiFilamentPlugin::make()
    ->navigationGroup('Integrações')           // padrão: "SEI"
    ->conexoes()                               // tela de conexões
    ->catalogos()                              // tela de catálogos
    ->logs(dias: 30)                           // log das chamadas; registros mais velhos são apagados aos poucos
    ->widget(false)                            // sem o widget no dashboard
    ->parametros()                             // tela de parâmetros
    ->blocos()                                 // tela de blocos
    ->vinculos()                               // processos vinculados e operações (desligado por padrão)
    ->sip()                                    // usuários e permissões no SIP (desligado por padrão)
    ->contatos()                               // contatos do SEI (desligado por padrão: dados pessoais)
    ->avisos(tipos: ['tramitado', 'concluido']) // avisos no sino (desligado por padrão)
    ->destinatariosDosAvisos(fn (SeiProcesso $processo, string $tipo) => [$processo->vinculavel?->responsavel])
    ->autorizarConfiguracao(fn (User $user) => $user->isAdmin())
    ->autorizarCatalogos(fn (User $user) => true)
    ->autorizarLogs(fn (User $user) => $user->isAdmin())
    ->autorizarVinculos(fn (User $user) => true)
    ->autorizarOperarProcessos(fn (User $user) => $user->isAdmin())
    ->autorizarResolverOperacoes(fn (User $user) => $user->isAdmin())
    ->autorizarBlocos(fn (User $user) => true)
    ->autorizarSip(fn (User $user) => $user->isAdmin())
    ->autorizarContatos(fn (User $user) => $user->isAdmin());
```

Vêm ligados: conexões, catálogos, parâmetros, blocos, log e widget. Vínculos, SIP, contatos e avisos são ligados pelo sistema. Sem `autorizarX`, cada tela pergunta ao `Gate` pela permissão da tabela acima (constante `PERMISSAO` da classe), o que funciona com o spatie/laravel-permission ou com uma policy/gate do sistema.

### Subclasse do app no lugar de uma tela

Cada tela aceita uma subclasse, por exemplo para implementar o `HasShieldPermissions` do Shield ou mudar colunas:

```php
SeiFilamentPlugin::make()
    ->conexoes(resource: App\Filament\Resources\ConexaoSeiResource::class)
    ->logs(resource: App\Filament\Resources\SeiLogResource::class)
    ->catalogos(pagina: App\Filament\Pages\CatalogosSei::class)
    ->blocos(pagina: App\Filament\Pages\BlocosSei::class)
    ->vinculos(processos: App\Filament\Resources\ProcessoSeiResource::class, operacoes: App\Filament\Resources\OperacaoSeiResource::class);
```

A subclasse precisa estender a classe do plugin.

## Conexões no banco

As conexões cadastradas na tela entram em `config('sei.connections.{nome}')` (e `sei.sip.connections.{nome}` com o SIP preenchido) **por cima** do `config/sei.php`:

- Mesmo nome de uma conexão do config (ex.: `default`) troca os dados dela; o que o banco não tem continua vindo do config. Os `parametros` se somam: um a um, os do banco valem por cima.
- **Conexão padrão** vira o `sei.default`: é a usada por `Sei::...` sem `connection()`.
- Desativar ou excluir faz voltar a valer o config/.env.
- As chaves (`identificacao_servico`, `sip_chave_acesso`) ficam criptografadas com a `APP_KEY` e nunca voltam para o navegador: na edição, em branco mantém a atual.
- O banco só é lido quando o SEI é usado no processo, e uma vez só. **Workers de fila** (e Octane) leem na primeira chamada e guardam: depois de mudar uma conexão, rode `php artisan queue:restart`.

**Testar** carrega a WSDL, confere as operações do conector com ela e valida as credenciais listando as unidades liberadas para o serviço (sem cache). O resultado fica gravado na conexão e aparece no widget.

## Catálogos

A tela consulta os catálogos com o cache do conector (`sei.cache`). Para unidades e usuários vale a unidade escolhida (`naUnidade`); em tipos de documento, de processo, de conferência e de prioridade ela filtra o catálogo. Clique no ID para copiar.

**Limpar cache** vale para todas as conexões: o conector não tem flush, então o plugin versiona o prefixo (`sei.cache.prefix` passa a `sei:v2`, `sei:v3`...) e as chaves antigas expiram sozinhas.

## Log das chamadas

Gravado pelos eventos do conector (`sei.events`, ligado por padrão), inclusive das chamadas da fila e do agendador. Grava operação, conexão, tempo, usuário, erro e os argumentos como o conector os registra: chave de acesso e dados pessoais mascarados (`sei.log.dados_pessoais`), Base64 resumido. O retorno do SEI não é gravado.

Com `->logs(false)` a tela some e nada é gravado.

## Processos vinculados e operações

Telas do módulo de persistência do conector (`Sei::integracao()`), que é opcional: publique as migrations dele antes de ligar.

```bash
php artisan vendor:publish --tag=sei-migrations
php artisan migrate
```

```php
SeiFilamentPlugin::make()->vinculos();
```

- **Processos vinculados**: processo, registro do sistema (com link para a tela dele, se houver um resource), nível de acesso, situação, onde está aberto e a última sincronização. Na tela do processo, os documentos com assinatura e publicação.
  - **Sincronizar** faz o mesmo que o `sei:sincronizar` com o processo, na hora, e religa a sincronização que o conector desligou por erro.
- Na tela do processo, **Operar no SEI** (permissão `operar_processos:sei`) inclui documento, envia, conclui e reabre; nos documentos, **Incluir em bloco de assinatura** (também em massa) e **Agendar/Cancelar publicação**.
- **Operações**: as chamadas com idempotência (`gerarProcesso`, `incluirDocumento`...). Uma operação **incerta** (timeout depois do envio: o SEI pode ou não ter executado) trava novas tentativas com a mesma chave; o menu mostra quantas há.
  - **Resolver** faz o mesmo que o `sei:operacoes:resolver`, depois de conferir no SEI: informar o processo ou documento que o SEI criou (vira vínculo), marcar como executada, ou descartar para liberar nova tentativa.

Os models seguem `sei.persistencia.models` do conector, inclusive subclasses do sistema.

## Gerar processo e incluir documento

O model do sistema usa o `HasSeiProcesso` do conector. As ações passam pelo `Sei::integracao()`: com idempotência (um clique duplo não gera dois processos) e com o vínculo gravado em `sei_processos`/`sei_documentos`.

```php
use MonteiroTI\SeiFilament\Actions\ConcluirProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\EnviarProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\GerarProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\IncluirDocumentoSeiAction;
use MonteiroTI\SeiFilament\Actions\ReabrirProcessoSeiAction;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

protected function getHeaderActions(): array
{
    return [
        GerarProcessoSeiAction::make(
            preencher: fn (Ferias $record) => [
                'id_tipo_procedimento' => Sei::parametro('tipos_processo.ferias'),
                'especificacao' => "Férias de {$record->servidor->nome}",
                'interessados' => [['nome' => $record->servidor->nome, 'documento' => $record->servidor->cpf]],
            ],
            documentos: fn (Ferias $record) => [
                Documento::gerado(Sei::parametro('series.requerimento'), view('ferias.requerimento', ['ferias' => $record])->render()),
            ],
            depois: fn (SeiProcesso $processo, Ferias $record) => $record->update(['status' => 'no_sei']),
        ),
        IncluirDocumentoSeiAction::make(preencher: fn () => ['id_serie' => Sei::parametro('series.despacho')]),
        EnviarProcessoSeiAction::make(destinos: [Sei::parametro('unidades.rh')]),
        ConcluirProcessoSeiAction::make(),
        ReabrirProcessoSeiAction::make(),
    ];
}
```

- **Gerar** some quando o registro já tem processo na conexão (um por registro, a chave padrão do conector). Passe `chave:` se o mesmo registro puder gerar vários.
- **Incluir documento**: gerado (conteúdo HTML, editável no SEI até a assinatura) ou externo (arquivo, data e remetente). Arquivo maior que `upload_chunk_size` sobe antes, em partes. Cada abertura do formulário tem a sua chave: clicar duas vezes em "Incluir" não duplica.
- `naFila: true` em Gerar e Incluir usa os jobs do conector (`GerarProcessoSeiJob`, `IncluirDocumentoSeiJob`).
- Timeout depois do envio vira operação **incerta**: a notificação diz para conferir em **Operações**.
- Todas aceitam `conexao:` e `unidade:` (fixas ou `fn ($record) => ...`).

## Blocos de assinatura

```php
// Num documento (SeiDocumento ou registro com o atributo documento_sei)
IncluirEmBlocoSeiAction::tabela(documento: fn (Portaria $record) => $record->numero_sei);

// Vários documentos num bloco só
->bulkActions([IncluirEmBlocoSeiAction::emMassa(documento: fn (Portaria $record) => $record->numero_sei)])
```

A tela **Blocos** consulta o bloco pelo número (o SEI não lista os blocos), mostra os documentos com as assinaturas e tem as ações do bloco: disponibilizar, cancelar a disponibilização, devolver, concluir, reabrir, excluir e retirar documento. A notificação da ação leva direto ao bloco.

## Publicação

```php
AgendarPublicacaoSeiAction::tabela(veiculo: Sei::parametro('veiculos.diario'), documento: fn (Portaria $record) => $record->numero_sei);
CancelarPublicacaoSeiAction::tabela(documento: fn (Portaria $record) => $record->numero_sei);
```

O documento precisa estar assinado. A sincronização do conector acompanha a publicação depois (e o aviso "Documento publicado" sai dela).

## Ouvidoria

```php
RegistrarOuvidoriaSeiAction::make(orgao: Sei::parametro('orgaos.principal'), preencher: fn (Atendimento $record) => [
    'nome' => $record->nome, 'email' => $record->email, 'cpf' => $record->cpf, 'mensagem' => $record->relato,
]);
```

Os tipos de manifestação vêm do SEI (`listarTiposProcedimentoOuvidoria`). Manifestação identificada precisa do CPF ou do RG com o órgão expedidor.

## Parâmetros

A tela mostra os `parametros` da conexão (`Sei::parametro('series.oficio')`) com o nome no SEI e a situação, o mesmo que o `sei:parametros:validar`. Com a conexão cadastrada em **Conexões**, **Editar parâmetros** grava no banco, escolhendo o ID nos catálogos; um a um, os do banco valem por cima dos do config.

## SIP

```php
SeiFilamentPlugin::make()->sip();

// Na tabela de usuários do sistema
ReplicarUsuarioSipAction::tabela(dados: fn (User $record) => [
    'id_origem' => $record->id, 'sigla' => $record->login, 'nome' => $record->name, 'cpf' => $record->cpf, 'email' => $record->email,
]);
```

Precisa de uma conexão SIP: a seção **SIP** da conexão ou `sei.sip` no config. A tela busca as permissões (por usuário, unidade ou perfil; sem filtro o SIP devolve todas), concede com vigência e retira. Os erros que o SIP acumula aparecem linha a linha.

## Contatos

`->contatos()` liga a tela: busca por nome, sigla, CPF, CNPJ (alfanumérico também) ou matrícula, em páginas de 25. **Editar** muda nome, e-mail, telefones, endereço e observação; o resto do contato vai como o SEI devolveu (o `atualizarContatos` troca o contato inteiro). Também desativa e reativa.

## Avisos no sino

```php
$panel
    ->databaseNotifications()
    ->plugin(SeiFilamentPlugin::make()->vinculos()->avisos()->destinatariosDosAvisos(
        fn (SeiProcesso $processo, string $tipo) => [$processo->vinculavel?->responsavel],
    ));
```

Saem da sincronização do conector (`sei:sincronizar`, na fila ou no agendador), para quem `destinatariosDosAvisos` devolver; sem ela, para quem tem `receber_avisos:sei` pelo spatie/laravel-permission. Precisam da tabela `notifications` do Laravel (`php artisan make:notifications-table`).

| Tipo | Quando |
|---|---|
| `tramitado` | O processo mudou de unidade |
| `concluido` / `reaberto` | O processo foi concluído ou reaberto |
| `andamento` | Qualquer andamento novo (fora do padrão: é muito) |
| `assinado` | Documento vinculado recebeu assinatura |
| `publicacao_agendada` / `publicado` | Publicação do documento |
| `falha` | O SEI recusou a consulta e a sincronização do processo parou |

O aviso leva para a tela do processo (com `->vinculos()`) e para o SEI.

## Consultar processo

```php
use MonteiroTI\SeiFilament\Actions\ConsultarProcessoSeiAction;

// Cabeçalho da página (Filament\Actions)
protected function getHeaderActions(): array
{
    return [ConsultarProcessoSeiAction::make()];
}

// Tabela e infolist: no Filament 3 cada lugar tem a sua classe de ação
->actions([ConsultarProcessoSeiAction::tabela(protocolo: fn (Processo $record) => $record->numero_sei)])
InfolistActions::make([ConsultarProcessoSeiAction::infolist(conexao: 'homologacao')])
```

- Sem `protocolo`, a ação usa o processo vinculado pelo `HasSeiProcesso` do conector (`$record->seiProcesso`, com a conexão do vínculo) ou o atributo `protocolo_sei`. Sem protocolo, a ação some.
- Mostra a geração, a conclusão e o último andamento. Para listar mais, passe as tarefas: `ConsultarProcessoSeiAction::make(tarefas: [1, 32])`.
- Processo sigiloso o SEI não devolve; o erro aparece no slide-over.

## Selects

```php
use MonteiroTI\SeiFilament\Forms\Components\SerieSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\UnidadeSeiSelect;

UnidadeSeiSelect::make('id_unidade')->live(),
SerieSeiSelect::make('id_serie')
    ->unidade(fn (Get $get) => $get('id_unidade'))
    ->conexao('homologacao')
    ->required(),
```

As opções vêm do cache do conector. Com o SEI fora do ar, ou a operação não liberada na conexão, o select fica vazio e mostra o motivo no hint; o formulário abre normalmente.

## Rótulos dos enums

```php
use MonteiroTI\SeiFilament\Support\Rotulos;
use MonteiroTi\Sei\Enum\NivelAcesso;

Select::make('nivel_acesso')->options(Rotulos::opcoes(NivelAcesso::class));

TextColumn::make('nivel')
    ->badge()
    ->formatStateUsing(fn (NivelAcesso $state) => Rotulos::de($state))
    ->color(fn (NivelAcesso $state) => Rotulos::cor($state));
```

## Testes

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
```

Os testes usam o `Sei::fake()` do conector: nada sai para a rede.
