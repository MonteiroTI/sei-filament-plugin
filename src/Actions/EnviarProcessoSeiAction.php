<?php

namespace MonteiroTI\SeiFilament\Actions;

use Closure;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Options\EnviarProcessoOptions;
use MonteiroTI\SeiFilament\Actions\Concerns\FabricasDeAcao;
use MonteiroTI\SeiFilament\Forms\Components\UnidadeSeiSelect;
use MonteiroTI\SeiFilament\Support\Erros;
use MonteiroTI\SeiFilament\Support\ProcessoDoRegistro;
use Throwable;

/**
 * Envia (tramita) o processo do registro para outras unidades (Sei::integracao()->enviarProcesso), uma vez por
 * abertura do formulário. A próxima sincronização atualiza onde ele está aberto.
 */
final class EnviarProcessoSeiAction
{
    use FabricasDeAcao;

    public const NOME = 'enviarProcessoSei';

    /**
     * @param  (Closure(Model): ?string)|string|null  $conexao
     * @param  (Closure(Model): ?string)|string|null  $unidade  unidade de onde o processo sai; padrão: a da conexão
     * @param  (Closure(Model): list<string>)|list<string>|null  $destinos  unidades já marcadas no formulário
     */
    protected static function configurar(
        MountableAction $acao,
        Closure|string|null $conexao = null,
        Closure|string|null $unidade = null,
        Closure|array|null $destinos = null,
    ): MountableAction {
        $conexaoDo = fn (?Model $record): ?string => self::avaliar($acao, $conexao, ['record' => $record]) ?: null;
        $unidadeDo = fn (?Model $record): ?string => self::avaliar($acao, $unidade, ['record' => $record]) ?: null;
        $processoDo = fn (?Model $record): ?SeiProcesso => ProcessoDoRegistro::de($record, $conexaoDo($record));

        return $acao
            ->label('Enviar processo')
            ->icon('heroicon-o-paper-airplane')
            ->color('gray')
            ->hidden(fn (?Model $record) => ! ($processoDo($record)?->aberto ?? false))
            ->modalHeading(fn (?Model $record) => 'Enviar o processo '.$processoDo($record)?->protocolo_formatado)
            ->modalSubmitActionLabel('Enviar')
            ->fillForm(fn (?Model $record) => [
                'chave' => (string) Str::uuid(),
                'unidades' => (array) (self::avaliar($acao, $destinos, ['record' => $record]) ?? []),
                'manter_aberto' => false,
                'enviar_email' => false,
            ])
            ->form(fn (?Model $record) => [
                Forms\Components\Hidden::make('chave'),
                UnidadeSeiSelect::make('unidades')
                    ->label('Unidades de destino')
                    ->conexao($conexaoDo($record) ?? $processoDo($record)?->conexao)
                    ->multiple()
                    ->required(),
                Forms\Components\Toggle::make('manter_aberto')
                    ->label('Manter aberto nesta unidade'),
                Forms\Components\Toggle::make('enviar_email')
                    ->label('Avisar as unidades por e-mail'),
                Forms\Components\TextInput::make('dias_retorno')
                    ->label('Retorno programado (dias)')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Opcional: prazo para o processo voltar.'),
            ])
            ->action(function (?Model $record, array $data, MountableAction $action) use ($conexaoDo, $unidadeDo, $processoDo) {
                $processo = $processoDo($record);

                try {
                    $integracao = app(SeiManager::class)->integracao($conexaoDo($record) ?? $processo->conexao);
                    $integracao = ($u = $unidadeDo($record) ?? $processo->id_unidade_geradora) !== null ? $integracao->naUnidade($u) : $integracao;
                    $integracao->enviarProcesso(
                        $processo,
                        array_values(array_map('strval', (array) $data['unidades'])),
                        'enviarProcesso:'.$processo->getKey().':'.$data['chave'],
                        new EnviarProcessoOptions(
                            manterAbertoUnidade: (bool) ($data['manter_aberto'] ?? false),
                            enviarEmailNotificacao: (bool) ($data['enviar_email'] ?? false),
                            diasRetornoProgramado: filled($data['dias_retorno'] ?? null) ? (int) $data['dias_retorno'] : null,
                        ),
                    );
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    Erros::notificar($e, 'Processo não enviado');
                    $action->halt();
                }

                Notification::make()->success()->title("Processo {$processo->protocolo_formatado} enviado")->send();
            });
    }
}
