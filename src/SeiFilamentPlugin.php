<?php

namespace MonteiroTI\SeiFilament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTI\SeiFilament\Avisos\AvisosDoSei;
use MonteiroTI\SeiFilament\Pages\BlocosSei;
use MonteiroTI\SeiFilament\Pages\CatalogosSei;
use MonteiroTI\SeiFilament\Pages\ContatosSei;
use MonteiroTI\SeiFilament\Pages\ParametrosSei;
use MonteiroTI\SeiFilament\Pages\SipSei;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\ConexaoSeiResource;
use MonteiroTI\SeiFilament\Resources\OperacoesSei\OperacaoSeiResource;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\ProcessoSeiResource;
use MonteiroTI\SeiFilament\Resources\SeiLogs\SeiLogResource;
use MonteiroTI\SeiFilament\Widgets\SituacaoSeiWidget;

/**
 * Telas do conector do SEI num painel do Filament:
 *
 * ```php
 * $panel->plugin(SeiFilamentPlugin::make()->navigationGroup('Integrações')->logs(dias: 30));
 * ```
 *
 * Tudo vem ligado. Cada tela pede a sua permissão (constante PERMISSAO da classe; troque com autorizarX) e aceita uma
 * subclasse do app no lugar da do plugin (ex.: para implementar o HasShieldPermissions do Shield):
 *
 * ```php
 * SeiFilamentPlugin::make()->conexoes(resource: App\Filament\Resources\ConexaoSeiResource::class);
 * ```
 */
class SeiFilamentPlugin implements Plugin
{
    public const ID = 'sei';

    private bool $conexoes = true;

    private bool $catalogos = true;

    private bool $logs = true;

    private int $diasDeLog = 90;

    private bool $widget = true;

    private bool $vinculos = false;

    private bool $blocos = true;

    private bool $parametros = true;

    private bool $sip = false;

    private bool $contatos = false;

    private ?Closure $autorizarContatos = null;

    private bool $avisos = false;

    /** @var list<string> */
    private array $tiposDeAviso = AvisosDoSei::PADRAO;

    private ?Closure $destinatariosDosAvisos = null;

    /**
     * Subclasses do app no lugar das telas do plugin, pela classe do plugin.
     *
     * @var array<class-string, class-string>
     */
    private array $classes = [];

    private string|Closure|null $navigationGroup = 'SEI';

    private ?Closure $autorizarConfiguracao = null;

    private ?Closure $autorizarCatalogos = null;

    private ?Closure $autorizarLogs = null;

    private ?Closure $autorizarVinculos = null;

    private ?Closure $autorizarResolverOperacoes = null;

    private ?Closure $autorizarBlocos = null;

    private ?Closure $autorizarOperarProcessos = null;

    private ?Closure $autorizarSip = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(self::ID);
    }

    /**
     * O plugin do painel atual ou, fora de um painel (fila, agendador, console), o do primeiro painel que o tem.
     */
    public static function configurado(): ?static
    {
        $atual = Filament::getCurrentPanel();
        $paineis = $atual?->hasPlugin(self::ID) ? [$atual] : Filament::getPanels();

        foreach ($paineis as $painel) {
            if ($painel->hasPlugin(self::ID)) {
                /** @var static */
                return $painel->getPlugin(self::ID);
            }
        }

        return null;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources(array_values(array_filter([
                $this->conexoes ? $this->recursoDeConexoes() : null,
                $this->logs ? $this->recursoDeLogs() : null,
                $this->vinculos ? $this->recursoDeProcessos() : null,
                $this->vinculos ? $this->recursoDeOperacoes() : null,
            ])))
            ->pages(array_values(array_filter([
                $this->catalogos ? $this->paginaDeCatalogos() : null,
                $this->blocos ? $this->classes[BlocosSei::class] ?? BlocosSei::class : null,
                $this->parametros ? $this->classes[ParametrosSei::class] ?? ParametrosSei::class : null,
                $this->sip ? $this->classes[SipSei::class] ?? SipSei::class : null,
                $this->contatos ? $this->classes[ContatosSei::class] ?? ContatosSei::class : null,
            ])))
            ->widgets(array_values(array_filter([
                $this->widget ? SituacaoSeiWidget::class : null,
            ])));
    }

    public function boot(Panel $panel): void {}

    /**
     * Conexões do SEI guardadas no banco (sei_conexoes), com o teste de conexão. Valem por cima do config/sei.php.
     *
     * @param  class-string<ConexaoSeiResource>|null  $resource  subclasse do app no lugar da do plugin
     */
    public function conexoes(bool $ligado = true, ?string $resource = null): static
    {
        $this->conexoes = $ligado;
        $this->usarClasse(ConexaoSeiResource::class, $resource);

        return $this;
    }

    /**
     * Consulta dos catálogos do SEI (unidades, séries, tipos de processo...) e limpeza do cache deles.
     *
     * @param  class-string<CatalogosSei>|null  $pagina  subclasse do app no lugar da do plugin
     */
    public function catalogos(bool $ligado = true, ?string $pagina = null): static
    {
        $this->catalogos = $ligado;
        $this->usarClasse(CatalogosSei::class, $pagina);

        return $this;
    }

    /**
     * Log das chamadas ao SEI (sei_logs). Desligado, a tela some e nada é gravado. Registros com mais de $dias dias
     * são apagados aos poucos, durante a gravação.
     *
     * @param  class-string<SeiLogResource>|null  $resource  subclasse do app no lugar da do plugin
     */
    public function logs(bool $ligado = true, ?string $resource = null, int $dias = 90): static
    {
        $this->logs = $ligado;
        $this->diasDeLog = max(1, $dias);
        $this->usarClasse(SeiLogResource::class, $resource);

        return $this;
    }

    /**
     * Processos e documentos vinculados e operações com idempotência (módulo de persistência do conector:
     * php artisan vendor:publish --tag=sei-migrations). Vem desligado porque as tabelas são opcionais no conector.
     *
     * @param  class-string<ProcessoSeiResource>|null  $processos  subclasse do app no lugar da do plugin
     * @param  class-string<OperacaoSeiResource>|null  $operacoes  subclasse do app no lugar da do plugin
     */
    public function vinculos(bool $ligado = true, ?string $processos = null, ?string $operacoes = null): static
    {
        $this->vinculos = $ligado;
        $this->usarClasse(ProcessoSeiResource::class, $processos);
        $this->usarClasse(OperacaoSeiResource::class, $operacoes);

        return $this;
    }

    /**
     * Parâmetros da conexão (IDs do SEI usados pelo sistema): conferidos contra os catálogos e editáveis no banco. Usa a
     * permissão da configuração.
     *
     * @param  class-string<ParametrosSei>|null  $pagina  subclasse do app no lugar da do plugin
     */
    public function parametros(bool $ligado = true, ?string $pagina = null): static
    {
        $this->parametros = $ligado;
        $this->usarClasse(ParametrosSei::class, $pagina);

        return $this;
    }

    /**
     * Blocos de assinatura: consulta pelo número, documentos com as assinaturas e as ações do bloco.
     *
     * @param  class-string<BlocosSei>|null  $pagina  subclasse do app no lugar da do plugin
     */
    public function blocos(bool $ligado = true, ?string $pagina = null): static
    {
        $this->blocos = $ligado;
        $this->usarClasse(BlocosSei::class, $pagina);

        return $this;
    }

    /**
     * Usuários e permissões no SIP. Vem desligado: precisa de uma conexão SIP (seção SIP da conexão ou sei.sip).
     *
     * @param  class-string<SipSei>|null  $pagina  subclasse do app no lugar da do plugin
     */
    public function sip(bool $ligado = true, ?string $pagina = null): static
    {
        $this->sip = $ligado;
        $this->usarClasse(SipSei::class, $pagina);

        return $this;
    }

    /**
     * Avisos no sino (notificações de banco do Filament: $panel->databaseNotifications()) quando a sincronização do
     * conector vê o processo tramitar, concluir, reabrir, um documento ser assinado ou publicado, ou falhar.
     *
     * @param  list<string>  $tipos  chaves de AvisosDoSei::TIPOS; padrão: AvisosDoSei::PADRAO (sem "andamento")
     */
    public function avisos(bool $ligado = true, array $tipos = AvisosDoSei::PADRAO): static
    {
        $this->avisos = $ligado;
        $this->tiposDeAviso = array_values(array_intersect($tipos, array_keys(AvisosDoSei::TIPOS)));

        return $this;
    }

    /**
     * Quem recebe cada aviso. Padrão: quem tem a permissão AvisosDoSei::PERMISSAO pelo spatie/laravel-permission.
     *
     * ```php
     * ->destinatariosDosAvisos(fn (SeiProcesso $processo, string $tipo) => [$processo->vinculavel?->responsavel])
     * ```
     *
     * @param  Closure(SeiProcesso, string): iterable<Model|null>  $consulta
     */
    public function destinatariosDosAvisos(Closure $consulta): static
    {
        $this->destinatariosDosAvisos = $consulta;

        return $this;
    }

    public function avisosLigados(): bool
    {
        return $this->avisos;
    }

    /** @return list<string> */
    public function tiposDeAviso(): array
    {
        return $this->tiposDeAviso;
    }

    /**
     * @return Collection<int, Model>
     */
    public function destinatariosDoAviso(SeiProcesso $processo, string $tipo): Collection
    {
        $usuarios = $this->destinatariosDosAvisos !== null
            ? collect(($this->destinatariosDosAvisos)($processo, $tipo))
            : AvisosDoSei::comPermissao();

        return $usuarios->filter(fn ($u) => $u instanceof Model)->unique(fn (Model $u) => $u->getKey())->values();
    }

    /**
     * Contatos do SEI: busca, edição, desativação e reativação. Vem desligado: são dados pessoais.
     *
     * @param  class-string<ContatosSei>|null  $pagina  subclasse do app no lugar da do plugin
     */
    public function contatos(bool $ligado = true, ?string $pagina = null): static
    {
        $this->contatos = $ligado;
        $this->usarClasse(ContatosSei::class, $pagina);

        return $this;
    }

    /**
     * Quem usa a tela de contatos. Padrão: Gate com ContatosSei::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarContatos(Closure $regra): static
    {
        $this->autorizarContatos = $regra;

        return $this;
    }

    /** Widget da situação do SEI no dashboard do painel. */
    public function widget(bool $ligado = true): static
    {
        $this->widget = $ligado;

        return $this;
    }

    public function navigationGroup(string|Closure|null $grupo): static
    {
        $this->navigationGroup = $grupo;

        return $this;
    }

    /**
     * Quem cadastra, edita e testa as conexões. Padrão: Gate com ConexaoSeiResource::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarConfiguracao(Closure $regra): static
    {
        $this->autorizarConfiguracao = $regra;

        return $this;
    }

    /**
     * Quem consulta os catálogos. Padrão: Gate com CatalogosSei::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarCatalogos(Closure $regra): static
    {
        $this->autorizarCatalogos = $regra;

        return $this;
    }

    /**
     * Quem vê o log das chamadas. Padrão: Gate com SeiLogResource::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarLogs(Closure $regra): static
    {
        $this->autorizarLogs = $regra;

        return $this;
    }

    /**
     * Quem vê os processos vinculados e as operações (e sincroniza um processo). Padrão: Gate com
     * ProcessoSeiResource::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarVinculos(Closure $regra): static
    {
        $this->autorizarVinculos = $regra;

        return $this;
    }

    /**
     * Quem resolve uma operação incerta depois de conferir no SEI. Padrão: Gate com OperacaoSeiResource::PERMISSAO_RESOLVER.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarResolverOperacoes(Closure $regra): static
    {
        $this->autorizarResolverOperacoes = $regra;

        return $this;
    }

    /**
     * Quem usa a tela de blocos. Padrão: Gate com BlocosSei::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarBlocos(Closure $regra): static
    {
        $this->autorizarBlocos = $regra;

        return $this;
    }

    /**
     * Quem inclui documento, envia, conclui, reabre e põe em bloco nas telas de processos vinculados do plugin (nas
     * telas do sistema, a autorização das ações é do sistema). Padrão: Gate com ProcessoSeiResource::PERMISSAO_OPERAR.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarOperarProcessos(Closure $regra): static
    {
        $this->autorizarOperarProcessos = $regra;

        return $this;
    }

    /**
     * Quem usa a tela do SIP. Padrão: Gate com SipSei::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarSip(Closure $regra): static
    {
        $this->autorizarSip = $regra;

        return $this;
    }

    public function temBlocos(): bool
    {
        return $this->blocos;
    }

    public function temVinculos(): bool
    {
        return $this->vinculos;
    }

    public function temLogs(): bool
    {
        return $this->logs;
    }

    public function diasDeLog(): int
    {
        return $this->diasDeLog;
    }

    public function temConexoes(): bool
    {
        return $this->conexoes;
    }

    /** @return class-string<ConexaoSeiResource> */
    public function recursoDeConexoes(): string
    {
        return $this->classes[ConexaoSeiResource::class] ?? ConexaoSeiResource::class;
    }

    /** @return class-string<SeiLogResource> */
    public function recursoDeLogs(): string
    {
        return $this->classes[SeiLogResource::class] ?? SeiLogResource::class;
    }

    /** @return class-string<ProcessoSeiResource> */
    public function recursoDeProcessos(): string
    {
        return $this->classes[ProcessoSeiResource::class] ?? ProcessoSeiResource::class;
    }

    /** @return class-string<OperacaoSeiResource> */
    public function recursoDeOperacoes(): string
    {
        return $this->classes[OperacaoSeiResource::class] ?? OperacaoSeiResource::class;
    }

    /** @return class-string<CatalogosSei> */
    public function paginaDeCatalogos(): string
    {
        return $this->classes[CatalogosSei::class] ?? CatalogosSei::class;
    }

    /**
     * Classe registrada no painel (a do app, se houver) para uma tela do plugin.
     *
     * @template T
     *
     * @param  class-string<T>  $classe
     * @return class-string<T>
     */
    public static function classeEmUso(string $classe): string
    {
        return static::configurado()?->classes[$classe] ?? $classe;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup instanceof Closure ? ($this->navigationGroup)() : $this->navigationGroup;
    }

    public function podeConfigurar(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarConfiguracao, ConexaoSeiResource::PERMISSAO);
    }

    public function podeConsultarCatalogos(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarCatalogos, CatalogosSei::PERMISSAO);
    }

    public function podeVerLogs(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarLogs, SeiLogResource::PERMISSAO);
    }

    public function podeVerVinculos(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarVinculos, ProcessoSeiResource::PERMISSAO);
    }

    public function podeGerenciarBlocos(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarBlocos, BlocosSei::PERMISSAO);
    }

    public function podeOperarProcessos(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarOperarProcessos, ProcessoSeiResource::PERMISSAO_OPERAR);
    }

    public function podeAdministrarSip(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarSip, SipSei::PERMISSAO);
    }

    public function podeGerenciarContatos(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarContatos, ContatosSei::PERMISSAO);
    }

    public function podeResolverOperacoes(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarResolverOperacoes, OperacaoSeiResource::PERMISSAO_RESOLVER);
    }

    private function pode(?Authenticatable $user, ?Closure $regra, string $permissao): bool
    {
        if ($user === null) {
            return false;
        }

        return $regra !== null ? (bool) $regra($user) : Gate::forUser($user)->allows($permissao);
    }

    private function usarClasse(string $base, ?string $classe): void
    {
        if ($classe === null) {
            return;
        }

        if (! is_a($classe, $base, true)) {
            throw new InvalidArgumentException("{$classe} precisa estender {$base}.");
        }

        $this->classes[$base] = $classe;
    }
}
