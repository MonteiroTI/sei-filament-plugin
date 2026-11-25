<?php

namespace MonteiroTI\SeiFilament\Support;

use Filament\Notifications\Notification;
use InvalidArgumentException;
use MonteiroTi\Sei\Core\Exceptions\AccessDeniedException;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Laravel\Integracao\OperacaoEmAndamentoException;
use MonteiroTi\Sei\Laravel\Integracao\OperacaoIncertaException;
use Throwable;

/**
 * Erros do conector em texto para a tela. Pega também o InvalidArgumentException da validação local, que não estende
 * SeiException (ex.: unidade não configurada, conexão inexistente).
 */
final class Erros
{
    public static function trata(Throwable $e): bool
    {
        return $e instanceof SeiException || $e instanceof InvalidArgumentException;
    }

    public static function titulo(Throwable $e): string
    {
        return match (true) {
            $e instanceof OperacaoIncertaException => 'O SEI pode ter executado a operação: confira em Operações antes de tentar de novo',
            $e instanceof OperacaoEmAndamentoException => 'A mesma operação já está em andamento',
            $e instanceof AccessDeniedException => 'Acesso negado pelo SEI',
            $e instanceof ConnectionException => 'Não foi possível falar com o SEI',
            $e instanceof FaultException => 'O SEI recusou a operação',
            $e instanceof InvalidArgumentException => 'Configuração ou dados incompletos',
            default => 'Erro no SEI',
        };
    }

    public static function mensagem(Throwable $e): string
    {
        $linhas = $e instanceof FaultException ? $e->messages() : [$e->getMessage()];

        return implode("\n", array_filter(array_map('trim', $linhas))) ?: $e::class;
    }

    public static function notificar(Throwable $e, ?string $titulo = null): void
    {
        Notification::make()
            ->danger()
            ->title($titulo ?? self::titulo($e))
            // Com título próprio, o motivo (ex.: operação incerta) vai no corpo.
            ->body(nl2br(e(($titulo !== null ? self::titulo($e).".\n" : '').self::mensagem($e))))
            ->persistent()
            ->send();
    }
}
