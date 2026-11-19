<?php

namespace MonteiroTI\SeiFilament\Logs;

use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Lottery;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Laravel\Events\SeiRequestFailed;
use MonteiroTi\Sei\Laravel\Events\SeiRequestSending;
use MonteiroTi\Sei\Laravel\Events\SeiResponseReceived;
use MonteiroTI\SeiFilament\Models\SeiLog;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;

/**
 * Grava cada chamada ao SEI/SIP em sei_logs, a partir dos eventos do conector (sei.events ligado, o padrão).
 *
 * Os argumentos vêm do evento de envio, já mascarados pelo conector; o resultado não é gravado (pode trazer o
 * conteúdo de documentos). Só grava com o log ligado no plugin.
 */
class GravarChamadasSei
{
    /** @var array<string, list<array<string, mixed>>> argumentos à espera da resposta, por conexão e operação */
    private array $pendentes = [];

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(SeiRequestSending::class, [$this, 'enviando']);
        $events->listen(SeiResponseReceived::class, [$this, 'recebido']);
        $events->listen(SeiRequestFailed::class, [$this, 'falhou']);
    }

    public function enviando(SeiRequestSending $evento): void
    {
        if ($this->ligado()) {
            $this->pendentes[$evento->connection.'|'.$evento->operation][] = $evento->arguments;
        }
    }

    public function recebido(SeiResponseReceived $evento): void
    {
        $this->gravar($evento->connection, $evento->operation, $evento->durationMs, [
            'sucesso' => true,
        ]);
    }

    public function falhou(SeiRequestFailed $evento): void
    {
        $e = $evento->exception;

        $this->gravar($evento->connection, $evento->operation, $evento->durationMs, [
            'sucesso' => false,
            'erro_classe' => $e::class,
            'erro_mensagem' => mb_substr($e->getMessage(), 0, 5000),
            'fault_code' => $e instanceof FaultException ? $e->faultCode : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function gravar(string $conexao, string $operacao, float $duracaoMs, array $dados): void
    {
        $chave = $conexao.'|'.$operacao;
        $argumentos = isset($this->pendentes[$chave]) ? array_pop($this->pendentes[$chave]) : null;

        $plugin = SeiFilamentPlugin::configurado();
        if ($plugin === null || ! $plugin->temLogs()) {
            return;
        }

        try {
            SeiLog::query()->create([
                'conexao' => $conexao,
                'operacao' => $operacao,
                'duracao_ms' => (int) round($duracaoMs),
                'argumentos' => $argumentos,
                'user_id' => Auth::id() !== null ? (string) Auth::id() : null,
                ...$dados,
            ]);

            // Poda aos poucos, sem depender do agendador.
            Lottery::odds(1, 100)->winner(fn () => SeiLog::query()
                ->where('created_at', '<', now()->subDays($plugin->diasDeLog()))
                ->limit(1000)
                ->delete())->choose();
        } catch (QueryException $e) {
            // Sem a tabela (migration não publicada) o log não pode derrubar a chamada ao SEI.
            report($e);
        }
    }

    private function ligado(): bool
    {
        return SeiFilamentPlugin::configurado()?->temLogs() ?? false;
    }
}
