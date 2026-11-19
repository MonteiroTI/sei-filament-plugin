<?php

namespace MonteiroTI\SeiFilament\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Uma chamada ao SEI/SIP, gravada pelos eventos do conector (Logs\GravarChamadasSei).
 *
 * @property int $id
 * @property string $conexao
 * @property string $operacao
 * @property bool $sucesso
 * @property int $duracao_ms
 * @property array<string, mixed>|null $argumentos
 * @property string|null $erro_classe
 * @property string|null $erro_mensagem
 * @property string|null $fault_code
 * @property string|null $user_id
 * @property Carbon|null $created_at
 */
class SeiLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sei_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sucesso' => 'boolean',
            'duracao_ms' => 'integer',
            'argumentos' => 'array',
        ];
    }

    /** Nome da conexão sem o prefixo do conector (sei:producao → producao). */
    public function nomeDaConexao(): string
    {
        return str_contains($this->conexao, ':') ? substr($this->conexao, strpos($this->conexao, ':') + 1) : $this->conexao;
    }
}
