<?php

namespace MonteiroTI\SeiFilament\Actions;

use Closure;
use Filament\Actions\MountableAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Actions\Concerns\FabricasDeAcao;
use MonteiroTI\SeiFilament\Support\Erros;
use MonteiroTI\SeiFilament\Support\ProcessoDoRegistro;
use Throwable;

/**
 * Conclui o processo do registro na unidade (Sei::integracao()->concluirProcesso).
 */
final class ConcluirProcessoSeiAction
{
    use FabricasDeAcao;

    public const NOME = 'concluirProcessoSei';

    /**
     * @param  (Closure(Model): ?string)|string|null  $conexao
     * @param  (Closure(Model): ?string)|string|null  $unidade  unidade onde concluir; padrão: a geradora ou a da conexão
     */
    protected static function configurar(MountableAction $acao, Closure|string|null $conexao = null, Closure|string|null $unidade = null): MountableAction
    {
        $conexaoDo = fn (?Model $record): ?string => self::avaliar($acao, $conexao, ['record' => $record]) ?: null;
        $processoDo = fn (?Model $record): ?SeiProcesso => ProcessoDoRegistro::de($record, $conexaoDo($record));

        return $acao
            ->label('Concluir processo')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->hidden(fn (?Model $record) => ! ($processoDo($record)?->aberto ?? false))
            ->requiresConfirmation()
            ->modalHeading(fn (?Model $record) => 'Concluir o processo '.$processoDo($record)?->protocolo_formatado.'?')
            ->modalDescription('O processo é concluído na unidade. Ele pode ser reaberto depois.')
            ->action(function (?Model $record, MountableAction $action) use ($acao, $conexaoDo, $processoDo, $unidade) {
                $processo = $processoDo($record);

                try {
                    $integracao = app(SeiManager::class)->integracao($conexaoDo($record) ?? $processo->conexao);
                    $u = static::avaliar($acao, $unidade, ['record' => $record]) ?: $processo->id_unidade_geradora;
                    ($u !== null ? $integracao->naUnidade($u) : $integracao)->concluirProcesso($processo);
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    Erros::notificar($e, 'Processo não concluído');
                    $action->halt();
                }

                Notification::make()->success()->title("Processo {$processo->protocolo_formatado} concluído")->send();
            });
    }
}
