<?php

namespace MonteiroTI\SeiFilament\Avisos;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\DocumentoSeiAssinado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\DocumentoSeiPublicado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\NovoAndamentoSei;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiConcluido;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiReaberto;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiTramitado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\PublicacaoSeiAgendada;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\SincronizacaoSeiFalhou;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTI\SeiFilament\Notifications\AvisoSeiNotification;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Support\Erros;
use MonteiroTI\SeiFilament\Support\Vinculo;
use Throwable;

/**
 * Avisos no sino a partir dos eventos da sincronização do conector (sei:sincronizar). Registrado pelo service
 * provider, e não pelo painel, porque a sincronização roda na fila e no agendador. Só avisa se algum painel tem o
 * plugin com avisos() ligado; os links vão para as telas desse painel.
 */
class AvisosDoSei
{
    /** Quem recebe os avisos, sem destinatariosDosAvisos() (pelo spatie/laravel-permission). */
    public const PERMISSAO = 'receber_avisos:sei';

    /** Tipos de aviso, por chave. */
    public const TIPOS = [
        'tramitado' => 'Processo tramitado',
        'concluido' => 'Processo concluído',
        'reaberto' => 'Processo reaberto',
        'andamento' => 'Novo andamento',
        'assinado' => 'Documento assinado',
        'publicacao_agendada' => 'Publicação agendada',
        'publicado' => 'Documento publicado',
        'falha' => 'Falha na sincronização',
    ];

    /** Sem "andamento": cada tramitação e cada documento geram um, e o sino vira ruído. */
    public const PADRAO = ['tramitado', 'concluido', 'reaberto', 'assinado', 'publicacao_agendada', 'publicado', 'falha'];

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ProcessoSeiTramitado::class, [$this, 'tramitado']);
        $events->listen(ProcessoSeiConcluido::class, [$this, 'concluido']);
        $events->listen(ProcessoSeiReaberto::class, [$this, 'reaberto']);
        $events->listen(NovoAndamentoSei::class, [$this, 'andamento']);
        $events->listen(DocumentoSeiAssinado::class, [$this, 'assinado']);
        $events->listen(PublicacaoSeiAgendada::class, [$this, 'publicacaoAgendada']);
        $events->listen(DocumentoSeiPublicado::class, [$this, 'publicado']);
        $events->listen(SincronizacaoSeiFalhou::class, [$this, 'falhou']);
    }

    public function tramitado(ProcessoSeiTramitado $evento): void
    {
        $siglas = collect($evento->processo->unidades_abertas ?? [])
            ->map(fn ($u) => is_array($u) ? ($u['sigla'] ?? $u['id'] ?? null) : null)
            ->filter()
            ->join(', ');

        $this->avisar('tramitado', $evento->processo, 'Processo tramitado',
            $siglas !== '' ? "Agora aberto em: {$siglas}." : 'O processo não está mais aberto em nenhuma unidade.',
            'info', 'heroicon-o-arrows-right-left');
    }

    public function concluido(ProcessoSeiConcluido $evento): void
    {
        $unidade = $evento->andamentoConclusao?->unidade?->sigla;

        $this->avisar('concluido', $evento->processo, 'Processo concluído',
            $unidade !== null ? "Concluído em {$unidade}." : 'Concluído em todas as unidades.',
            'success', 'heroicon-o-check-circle');
    }

    public function reaberto(ProcessoSeiReaberto $evento): void
    {
        $this->avisar('reaberto', $evento->processo, 'Processo reaberto', 'O processo voltou a ficar aberto.', 'warning', 'heroicon-o-arrow-uturn-left');
    }

    public function andamento(NovoAndamentoSei $evento): void
    {
        $andamento = $evento->andamento;
        $texto = html_entity_decode(strip_tags((string) $andamento->descricao), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->avisar('andamento', $evento->processo, 'Novo andamento',
            trim(($andamento->unidade?->sigla ? $andamento->unidade->sigla.': ' : '').$texto),
            'gray', 'heroicon-o-clock');
    }

    public function assinado(DocumentoSeiAssinado $evento): void
    {
        $nomes = collect($evento->novas)->map(fn ($a) => $a->nome)->filter()->join(', ');

        $this->avisar('assinado', $evento->documento->processo, "Documento {$evento->documento->documento_formatado} assinado",
            $nomes !== '' ? "Assinado por {$nomes}." : 'Recebeu nova assinatura.',
            'success', 'heroicon-o-pencil-square', $evento->documento);
    }

    public function publicacaoAgendada(PublicacaoSeiAgendada $evento): void
    {
        $data = $evento->publicacao->dataDisponibilizacao?->format('d/m/Y');

        $this->avisar('publicacao_agendada', $evento->documento->processo, "Publicação do documento {$evento->documento->documento_formatado} agendada",
            trim(($evento->publicacao->nomeVeiculo ?? 'Publicação').($data ? " em {$data}" : '').'.'),
            'info', 'heroicon-o-calendar', $evento->documento);
    }

    public function publicado(DocumentoSeiPublicado $evento): void
    {
        $data = $evento->publicacao->dataPublicacao?->format('d/m/Y');

        $this->avisar('publicado', $evento->documento->processo, "Documento {$evento->documento->documento_formatado} publicado",
            trim(($evento->publicacao->nomeVeiculo ?? 'Publicado').($data ? " em {$data}" : '').($evento->publicacao->numero ? ", nº {$evento->publicacao->numero}" : '').'.'),
            'success', 'heroicon-o-newspaper', $evento->documento);
    }

    public function falhou(SincronizacaoSeiFalhou $evento): void
    {
        $registro = $evento->registro;
        $processo = $registro instanceof SeiDocumento ? $registro->processo : $registro;

        $this->avisar('falha', $processo, 'Sincronização com o SEI parou',
            Erros::mensagem($evento->exception).' A sincronização deste processo foi desligada; use "Sincronizar" na tela do processo depois de resolver.',
            'danger', 'heroicon-o-exclamation-triangle', $registro instanceof SeiDocumento ? $registro : null);
    }

    private function avisar(string $tipo, ?SeiProcesso $processo, string $titulo, string $corpo, string $cor, string $icone, ?SeiDocumento $documento = null): void
    {
        if ($processo === null || ($painel = self::painel()) === null) {
            return;
        }

        /** @var SeiFilamentPlugin $plugin */
        $plugin = $painel->getPlugin(SeiFilamentPlugin::ID);
        if (! in_array($tipo, $plugin->tiposDeAviso(), true)) {
            return;
        }

        try {
            $destinatarios = $plugin->destinatariosDoAviso($processo, $tipo);
            if ($destinatarios->isEmpty()) {
                return;
            }

            $registro = Vinculo::texto($processo->vinculavel_type, $processo->vinculavel_id);
            $url = $plugin->temVinculos()
                ? $plugin->recursoDeProcessos()::getUrl('view', ['record' => $processo], panel: $painel->getId())
                : null;

            Notification::send($destinatarios, new AvisoSeiNotification(
                $tipo,
                $titulo,
                "Processo {$processo->protocolo_formatado}".($registro ? " ({$registro})" : '').'. '.$corpo,
                $cor,
                $icone,
                $url,
                $documento?->link_acesso ?? $processo->link_acesso,
            ));
        } catch (Throwable $e) {
            // Um aviso que falha não pode derrubar a sincronização.
            report($e);
        }
    }

    /** Primeiro painel com o plugin e os avisos ligados. */
    private static function painel(): ?Panel
    {
        foreach (Filament::getPanels() as $painel) {
            if ($painel->hasPlugin(SeiFilamentPlugin::ID) && $painel->getPlugin(SeiFilamentPlugin::ID)->avisosLigados()) {
                return $painel;
            }
        }

        return null;
    }

    /**
     * Padrão dos destinatários: quem tem a permissão PERMISSAO pelo spatie/laravel-permission. Sem o pacote, ninguém:
     * use destinatariosDosAvisos().
     *
     * @return Collection<int, Model>
     */
    public static function comPermissao(): Collection
    {
        $modelo = config('auth.providers.users.model');

        if (! is_string($modelo) || ! method_exists($modelo, 'scopePermission')) {
            return collect();
        }

        try {
            return $modelo::permission(self::PERMISSAO)->get();
        } catch (Throwable $e) {
            // Permissão ainda não criada: ninguém a tem.
            if (is_a($e, 'Spatie\Permission\Exceptions\PermissionDoesNotExist')) {
                return collect();
            }

            throw $e;
        }
    }
}
