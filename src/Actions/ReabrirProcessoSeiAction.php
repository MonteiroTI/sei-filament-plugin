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
 * Reabre o processo do registro na unidade e sincroniza o vínculo na hora (a integração do conector não tem reabrir:
 * a chamada é direta, e reabrir de novo um processo aberto não tem efeito).
 */
final class ReabrirProcessoSeiAction
{
    use FabricasDeAcao;

    public const NOME = 'reabrirProcessoSei';

    /**
     * @param  (Closure(Model): ?string)|string|null  $conexao
     * @param  (Closure(Model): ?string)|string|null  $unidade  unidade onde reabrir; padrão: a geradora ou a da conexão
     */
    protected static function configurar(MountableAction $acao, Closure|string|null $conexao = null, Closure|string|null $unidade = null): MountableAction
    {
        $conexaoDo = fn (?Model $record): ?string => self::avaliar($acao, $conexao, ['record' => $record]) ?: null;
        $processoDo = fn (?Model $record): ?SeiProcesso => ProcessoDoRegistro::de($record, $conexaoDo($record));

        return $acao
            ->label('Reabrir processo')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->hidden(fn (?Model $record) => ($processo = $processoDo($record)) === null || $processo->aberto)
            ->requiresConfirmation()
            ->modalHeading(fn (?Model $record) => 'Reabrir o processo '.$processoDo($record)?->protocolo_formatado.'?')
            ->action(function (?Model $record, MountableAction $action) use ($acao, $conexaoDo, $processoDo, $unidade) {
                $processo = $processoDo($record);
                $manager = app(SeiManager::class);

                try {
                    $cliente = $manager->connection($conexaoDo($record) ?? $processo->conexao);
                    $u = static::avaliar($acao, $unidade, ['record' => $record]) ?: $processo->id_unidade_geradora;
                    ($u !== null ? $cliente->naUnidade($u) : $cliente)->processos()->reabrir($processo->protocolo_formatado);
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    Erros::notificar($e, 'Processo não reaberto');
                    $action->halt();
                }

                // O vínculo só muda na sincronização; um erro nela não desfaz a reabertura.
                try {
                    $processo->forceFill(['sincronizar' => true])->save();
                    $manager->sincronizador()->processo($processo);
                } catch (Throwable $e) {
                    report($e);
                }

                Notification::make()->success()->title("Processo {$processo->protocolo_formatado} reaberto")->send();
            });
    }
}
