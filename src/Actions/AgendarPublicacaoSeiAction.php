<?php

namespace MonteiroTI\SeiFilament\Actions;

use Closure;
use DateTimeImmutable;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use MonteiroTi\Sei\Enum\EstadoPublicacao;
use MonteiroTi\Sei\Enum\MotivoPublicacao;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Options\DadosPublicacao;
use MonteiroTI\SeiFilament\Actions\Concerns\FabricasDeAcao;
use MonteiroTI\SeiFilament\Support\DocumentoDoRegistro;
use MonteiroTI\SeiFilament\Support\Erros;
use MonteiroTI\SeiFilament\Support\Rotulos;
use Throwable;

/**
 * Agenda a publicação do documento (agendarPublicacao) no veículo do órgão. O documento precisa estar assinado e o
 * serviço precisa da operação liberada. A sincronização do conector acompanha a publicação depois.
 */
final class AgendarPublicacaoSeiAction
{
    use FabricasDeAcao;

    public const NOME = 'agendarPublicacaoSei';

    /**
     * @param  (Closure(Model): ?string)|string|null  $conexao
     * @param  (Closure(Model): ?string)|string|null  $unidade
     * @param  (Closure(Model): ?string)|null  $documento  número do documento no SEI
     * @param  (Closure(Model): ?string)|string|null  $veiculo  ID do veículo de publicação (ex.: Sei::parametro('veiculos.diario'))
     */
    protected static function configurar(
        MountableAction $acao,
        Closure|string|null $conexao = null,
        Closure|string|null $unidade = null,
        ?Closure $documento = null,
        Closure|string|null $veiculo = null,
    ): MountableAction {
        return $acao
            ->label('Agendar publicação')
            ->icon('heroicon-o-newspaper')
            ->color('gray')
            ->hidden(fn (?Model $record) => DocumentoDoRegistro::protocolo($acao, $documento, $record) === null
                || ($record instanceof SeiDocumento && in_array($record->publicacao_estado, [EstadoPublicacao::Agendado->value, EstadoPublicacao::Publicado->value], true)))
            ->modalHeading(fn (?Model $record) => 'Agendar a publicação do documento '.DocumentoDoRegistro::protocolo($acao, $documento, $record))
            ->modalSubmitActionLabel('Agendar')
            ->fillForm(fn (?Model $record) => [
                'motivo' => MotivoPublicacao::Publicacao->value,
                'id_veiculo' => self::avaliar($acao, $veiculo, ['record' => $record]),
                'data' => now()->addWeekday()->toDateString(),
            ])
            ->form([
                Forms\Components\Select::make('motivo')
                    ->options(Rotulos::opcoes(MotivoPublicacao::class))
                    ->selectablePlaceholder(false)
                    ->required(),
                Forms\Components\TextInput::make('id_veiculo')
                    ->label('Veículo de publicação (ID)')
                    ->required(),
                Forms\Components\DatePicker::make('data')
                    ->label('Data de disponibilização')
                    ->minDate(now()->startOfDay())
                    ->required(),
                Forms\Components\Textarea::make('resumo')
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->action(function (?Model $record, array $data, MountableAction $action) use ($acao, $conexao, $unidade, $documento) {
                $protocolo = (string) DocumentoDoRegistro::protocolo($acao, $documento, $record);

                try {
                    $cliente = app(SeiManager::class)->connection(static::avaliar($acao, $conexao, ['record' => $record]) ?: DocumentoDoRegistro::conexao($record));
                    $u = static::avaliar($acao, $unidade, ['record' => $record]) ?: null;
                    $idPublicacao = ($u !== null ? $cliente->naUnidade($u) : $cliente)->publicacoes()->agendar(new DadosPublicacao(
                        MotivoPublicacao::from((int) $data['motivo']),
                        (string) $data['id_veiculo'],
                        new DateTimeImmutable((string) $data['data']),
                        $data['resumo'] ?? null ?: null,
                    ), protocoloDocumento: $protocolo);
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    Erros::notificar($e, 'Publicação não agendada');
                    $action->halt();
                }

                // O vínculo só muda na sincronização; marca já para a tela não oferecer agendar de novo.
                if ($record instanceof SeiDocumento) {
                    $record->forceFill(['id_publicacao' => $idPublicacao, 'publicacao_estado' => EstadoPublicacao::Agendado->value])->save();
                }

                Notification::make()->success()->title("Publicação do documento {$protocolo} agendada")->body(new HtmlString(e("Publicação nº {$idPublicacao}.")))->send();
            });
    }
}
