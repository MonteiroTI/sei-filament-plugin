<?php

namespace MonteiroTI\SeiFilament\Resources\ConexoesSei;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use MonteiroTI\SeiFilament\Models\ConexaoSei;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\Pages\CreateConexaoSei;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\Pages\EditConexaoSei;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\Pages\ListConexoesSei;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Support\TesteDeConexao;

/**
 * Conexões do SEI no banco (sei_conexoes), por cima das do config/sei.php.
 */
class ConexaoSeiResource extends Resource
{
    public const PERMISSAO = 'editar_configuracao:sei';

    protected static ?string $model = ConexaoSei::class;

    protected static ?string $slug = 'sei-conexoes';

    protected static ?string $modelLabel = 'conexão do SEI';

    protected static ?string $pluralModelLabel = 'conexões do SEI';

    protected static ?string $navigationLabel = 'Conexões';

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function getNavigationGroup(): ?string
    {
        return Filament::getCurrentPanel()?->hasPlugin(SeiFilamentPlugin::ID)
            ? SeiFilamentPlugin::get()->getNavigationGroup()
            : null;
    }

    public static function canViewAny(): bool
    {
        return static::podeConfigurar();
    }

    public static function canCreate(): bool
    {
        return static::podeConfigurar();
    }

    public static function canEdit(Model $record): bool
    {
        return static::podeConfigurar();
    }

    public static function canDelete(Model $record): bool
    {
        return static::podeConfigurar();
    }

    public static function canDeleteAny(): bool
    {
        return static::podeConfigurar();
    }

    protected static function podeConfigurar(): bool
    {
        return SeiFilamentPlugin::configurado()?->podeConfigurar(Filament::auth()->user()) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('SEI')
                ->description('Sistema e serviço cadastrados no SEI em Administração › Sistemas › Serviços.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('nome')
                        ->required()
                        ->maxLength(60)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->helperText('Nome usado no código: Sei::connection(\'homologacao\'). Mesmo nome de uma conexão do config/sei.php troca os dados dela.'),
                    Forms\Components\Group::make([
                        Forms\Components\Toggle::make('padrao')
                            ->label('Conexão padrão')
                            ->helperText('Usada por Sei::... sem connection().'),
                        Forms\Components\Toggle::make('ativo')
                            ->default(true)
                            ->helperText('Desativada, volta a valer o config/sei.php.'),
                    ]),
                    Forms\Components\TextInput::make('wsdl')
                        ->label('WSDL')
                        ->required()
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://sei.orgao.gov.br/sei/controlador_ws.php?servico=sei')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('sigla_sistema')
                        ->label('Sigla do sistema')
                        ->required()
                        ->maxLength(60),
                    static::campoDeChave('identificacao_servico', 'Chave de acesso')
                        ->helperText('Gerada no cadastro do serviço; o SEI mostra uma vez só.'),
                    Forms\Components\TextInput::make('id_unidade')
                        ->label('ID da unidade padrão')
                        ->maxLength(30)
                        ->helperText('Unidade das chamadas que não usam naUnidade(). Veja os IDs em Catálogos.'),
                    Forms\Components\Select::make('conteudo_charset')
                        ->label('Charset do conteúdo')
                        ->options(['UTF-8' => 'UTF-8', 'ISO-8859-1' => 'ISO-8859-1'])
                        ->default('UTF-8')
                        ->selectablePlaceholder(false)
                        ->helperText('Do HTML dos documentos gerados, antes do Base64.'),
                ]),

            Forms\Components\Section::make('Permissões')
                ->description('Bloqueia no sistema o que o serviço não deve fazer. Libere as mesmas operações no cadastro do serviço no SEI.')
                ->collapsible()
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('somente_leitura')
                        ->label('Somente leitura')
                        ->helperText('Só consultar* e listar*.'),
                    Forms\Components\TagsInput::make('operacoes')
                        ->label('Operações liberadas')
                        ->placeholder('gerarProcedimento, consultar*...')
                        ->helperText('Vazio libera todas. Aceita curinga *.'),
                ]),

            Forms\Components\Section::make('Transporte')
                ->collapsible()
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('location')
                        ->label('Endpoint alternativo')
                        ->url()
                        ->maxLength(255)
                        ->helperText('No lugar do declarado na WSDL (ex.: atrás de proxy reverso).')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('timeout')
                        ->label('Timeout (s)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(600)
                        ->default(60)
                        ->required(),
                    Forms\Components\TextInput::make('connection_timeout')
                        ->label('Timeout de conexão (s)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(120)
                        ->default(10)
                        ->required(),
                    Forms\Components\Toggle::make('verify_ssl')
                        ->label('Verificar o certificado SSL')
                        ->default(true),
                ]),

            Forms\Components\Section::make('SIP (opcional)')
                ->description('Para replicar usuários e permissões. O SIP tem sistema e chave próprios.')
                ->collapsible()
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('sip_wsdl')
                        ->label('WSDL do SIP')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://sip.orgao.gov.br/sip/controlador_ws.php?servico=sip')
                        ->columnSpanFull(),
                    static::campoDeChave('sip_chave_acesso', 'Chave de acesso do SIP', obrigatoria: false)
                        ->requiredWith('sip_wsdl'),
                    Forms\Components\TextInput::make('sip_id_sistema')
                        ->label('ID do sistema no SIP')
                        ->maxLength(30),
                ]),
        ]);
    }

    /**
     * Chave criptografada: nunca volta para o navegador. Na edição, em branco mantém a atual.
     */
    protected static function campoDeChave(string $campo, string $rotulo, bool $obrigatoria = true): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($campo)
            ->label($rotulo)
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->formatStateUsing(fn () => null)
            ->dehydrated(fn (?string $state) => filled($state))
            ->required(fn (string $operation) => $obrigatoria && $operation === 'create')
            ->placeholder(fn (string $operation, ?ConexaoSei $record) => $operation === 'edit' && filled($record?->getAttribute($campo))
                ? '•••••••• (em branco mantém a atual)'
                : null);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->weight(fn (ConexaoSei $record) => $record->padrao ? 'bold' : null)
                    ->description(fn (ConexaoSei $record) => $record->padrao ? 'padrão' : null),
                Tables\Columns\TextColumn::make('wsdl')
                    ->label('WSDL')
                    ->limit(45)
                    ->tooltip(fn (ConexaoSei $record) => $record->wsdl)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('sigla_sistema')
                    ->label('Sigla'),
                Tables\Columns\TextColumn::make('id_unidade')
                    ->label('Unidade')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('somente_leitura')
                    ->label('Só leitura')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('ativo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('testada_em')
                    ->label('Último teste')
                    ->since()
                    ->placeholder('nunca')
                    ->badge()
                    ->color(fn (ConexaoSei $record) => match ($record->teste_ok) {
                        true => 'success',
                        false => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (ConexaoSei $record) => $record->resultado_teste),
            ])
            ->actions([
                static::acaoTestar(Tables\Actions\Action::make('testar')),
                Tables\Actions\Action::make('tornarPadrao')
                    ->label('Tornar padrão')
                    ->icon('heroicon-o-star')
                    ->color('gray')
                    ->visible(fn (ConexaoSei $record) => ! $record->padrao && $record->ativo)
                    ->requiresConfirmation()
                    ->modalDescription(fn (ConexaoSei $record) => "As chamadas sem connection() passam a usar \"{$record->nome}\".")
                    ->action(function (ConexaoSei $record) {
                        $record->update(['padrao' => true]);
                        Notification::make()->success()->title("\"{$record->nome}\" é a conexão padrão")->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhuma conexão no banco')
            ->emptyStateDescription('Valem as conexões do config/sei.php (.env). Cadastre uma para trocar as credenciais sem novo deploy.');
    }

    /**
     * "Testar conexão" na tabela e no cabeçalho da edição: o mesmo do sei:ping, com o resultado gravado na conexão.
     *
     * @template T of Tables\Actions\Action|\Filament\Actions\Action
     *
     * @param  T  $acao
     * @return T
     */
    public static function acaoTestar(Tables\Actions\Action|Action $acao): Tables\Actions\Action|Action
    {
        return $acao
            ->label('Testar')
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->visible(fn (ConexaoSei $record) => $record->ativo)
            ->action(function (ConexaoSei $record) {
                $teste = TesteDeConexao::executar($record->nome);

                $record->forceFill([
                    'testada_em' => now(),
                    'teste_ok' => $teste->ok,
                    'resultado_teste' => mb_substr($teste->texto(), 0, 5000),
                ])->saveQuietly();

                Notification::make()
                    ->status($teste->ok ? ($teste->avisos === [] ? 'success' : 'warning') : 'danger')
                    ->title($teste->ok ? "Conexão \"{$record->nome}\" ok" : "Conexão \"{$record->nome}\" falhou")
                    ->body(nl2br(e($teste->texto())))
                    ->persistent(! $teste->ok)
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConexoesSei::route('/'),
            'create' => CreateConexaoSei::route('/create'),
            'edit' => EditConexaoSei::route('/{record}/edit'),
        ];
    }
}
