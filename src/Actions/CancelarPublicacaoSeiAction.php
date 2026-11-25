<?php

namespace MonteiroTI\SeiFilament\Actions;

use Closure;
use Filament\Actions\MountableAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Enum\EstadoPublicacao;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Actions\Concerns\FabricasDeAcao;
use MonteiroTI\SeiFilament\Support\DocumentoDoRegistro;
use MonteiroTI\SeiFilament\Support\Erros;
use Throwable;

/**
 * Cancela o agendamento da publicação do documento (cancelarAgendamentoPublicacao), antes de ela sair.
 */
final class CancelarPublicacaoSeiAction
{
    use FabricasDeAcao;

    public const NOME = 'cancelarPublicacaoSei';

    /**
     * @param  (Closure(Model): ?string)|string|null  $conexao
     * @param  (Closure(Model): ?string)|string|null  $unidade
     * @param  (Closure(Model): ?string)|null  $documento  número do documento no SEI
     */
    protected static function configurar(MountableAction $acao, Closure|string|null $conexao = null, Closure|string|null $unidade = null, ?Closure $documento = null): MountableAction
    {
        return $acao
            ->label('Cancelar publicação')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->hidden(fn (?Model $record) => DocumentoDoRegistro::protocolo($acao, $documento, $record) === null
                || ($record instanceof SeiDocumento && $record->publicacao_estado !== EstadoPublicacao::Agendado->value))
            ->requiresConfirmation()
            ->modalHeading(fn (?Model $record) => 'Cancelar a publicação do documento '.DocumentoDoRegistro::protocolo($acao, $documento, $record).'?')
            ->action(function (?Model $record, MountableAction $action) use ($acao, $conexao, $unidade, $documento) {
                $protocolo = (string) DocumentoDoRegistro::protocolo($acao, $documento, $record);

                try {
                    $cliente = app(SeiManager::class)->connection(static::avaliar($acao, $conexao, ['record' => $record]) ?: DocumentoDoRegistro::conexao($record));
                    $u = static::avaliar($acao, $unidade, ['record' => $record]) ?: null;
                    ($u !== null ? $cliente->naUnidade($u) : $cliente)->publicacoes()->cancelarAgendamento(protocoloDocumento: $protocolo);
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    Erros::notificar($e, 'Publicação não cancelada');
                    $action->halt();
                }

                if ($record instanceof SeiDocumento) {
                    $record->forceFill(['id_publicacao' => null, 'publicacao_estado' => null])->save();
                }

                Notification::make()->success()->title("Publicação do documento {$protocolo} cancelada")->send();
            });
    }
}
