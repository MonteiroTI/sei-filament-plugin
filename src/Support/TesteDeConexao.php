<?php

namespace MonteiroTI\SeiFilament\Support;

use MonteiroTi\Sei\Core\Config\TransportOptions;
use MonteiroTi\Sei\Core\Transport\SoapTransport;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Operations\SeiOperations;
use MonteiroTi\Sei\Operations\WsdlContract;
use Throwable;

/**
 * O que o sei:ping faz, para a tela: carrega a WSDL, confere as operações e valida as credenciais listando as
 * unidades liberadas para o serviço (sem cache).
 */
final class TesteDeConexao
{
    /**
     * @param  list<string>  $avisos
     */
    private function __construct(
        public readonly bool $ok,
        public readonly string $resumo,
        public readonly array $avisos = [],
        public readonly ?Throwable $erro = null,
    ) {}

    public static function executar(string $conexao): self
    {
        $manager = app(SeiManager::class);
        $avisos = [];
        $inicio = hrtime(true);

        try {
            $config = $manager->connectionConfig($conexao);

            // Com Sei::fake() não há WSDL de verdade para carregar.
            if (! $manager->isFake()) {
                $relatorio = WsdlContract::compare(
                    (new SoapTransport(TransportOptions::fromArray($config)))->functions(),
                    SeiOperations::all(),
                    SeiOperations::parameters(...),
                );

                if ($relatorio['missing'] !== []) {
                    $avisos[] = 'Operações ausentes na WSDL (versão do SEI diferente?): '.implode(', ', $relatorio['missing']);
                }
                if ($relatorio['mismatched'] !== []) {
                    $avisos[] = 'Parâmetros diferentes da WSDL em: '.implode(', ', array_keys($relatorio['mismatched']));
                }
            }

            $cliente = $manager->connection($conexao);

            if (! $cliente->config()->permite('listarUnidades')) {
                return new self(true, 'WSDL carregada. listarUnidades não está liberada nesta conexão: credenciais não verificadas.', $avisos);
            }

            $unidades = count($cliente->catalogos(cache: false)->unidades());
            $ms = (int) round((hrtime(true) - $inicio) / 1e6);

            return new self(true, sprintf('Credenciais válidas: %d unidade(s) liberada(s) para o serviço (%d ms).', $unidades, $ms), $avisos);
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }

            return new self(false, Erros::titulo($e).': '.Erros::mensagem($e), $avisos, $e);
        }
    }

    public function texto(): string
    {
        return implode("\n", [$this->resumo, ...$this->avisos]);
    }
}
