<?php

namespace MonteiroTI\SeiFilament\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use MonteiroTI\SeiFilament\Support\ConexoesNoConfig;

/**
 * Conexão do SEI (e, opcional, do SIP) cadastrada pela tela. Entra em config('sei.connections.{nome}') por cima do
 * config/sei.php; as chaves de acesso ficam criptografadas com a APP_KEY.
 *
 * @property int $id
 * @property string $nome
 * @property bool $padrao
 * @property bool $ativo
 * @property string $wsdl
 * @property string|null $location
 * @property string $sigla_sistema
 * @property string $identificacao_servico
 * @property string|null $id_unidade
 * @property string $conteudo_charset
 * @property int $timeout
 * @property int $connection_timeout
 * @property bool $verify_ssl
 * @property bool $somente_leitura
 * @property list<string>|null $operacoes
 * @property string|null $sip_wsdl
 * @property string|null $sip_chave_acesso
 * @property string|null $sip_id_sistema
 * @property Carbon|null $testada_em
 * @property bool|null $teste_ok
 * @property string|null $resultado_teste
 */
class ConexaoSei extends Model
{
    protected $table = 'sei_conexoes';

    protected $guarded = ['id'];

    protected $hidden = ['identificacao_servico', 'sip_chave_acesso'];

    protected $attributes = [
        'padrao' => false,
        'ativo' => true,
        'conteudo_charset' => 'UTF-8',
        'timeout' => 60,
        'connection_timeout' => 10,
        'verify_ssl' => true,
        'somente_leitura' => false,
    ];

    protected function casts(): array
    {
        return [
            'padrao' => 'boolean',
            'ativo' => 'boolean',
            'identificacao_servico' => 'encrypted',
            'timeout' => 'integer',
            'connection_timeout' => 'integer',
            'verify_ssl' => 'boolean',
            'somente_leitura' => 'boolean',
            'operacoes' => 'array',
            'sip_chave_acesso' => 'encrypted',
            'testada_em' => 'datetime',
            'teste_ok' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Uma só padrão; e qualquer mudança vale já nesta request (o conector guarda os clientes criados).
        static::saving(function (ConexaoSei $conexao) {
            if ($conexao->padrao && $conexao->isDirty('padrao')) {
                static::query()->whereKeyNot($conexao->getKey() ?? 0)->where('padrao', true)->update(['padrao' => false]);
            }
        });

        static::saved(fn () => ConexoesNoConfig::recarregar());
        static::deleted(fn () => ConexoesNoConfig::recarregar());
    }

    public function temSip(): bool
    {
        return filled($this->sip_wsdl) && filled($this->sip_chave_acesso);
    }

    /**
     * Chaves de config('sei.connections.{nome}') que vêm do banco.
     *
     * @return array<string, mixed>
     */
    public function paraConfigSei(): array
    {
        return [
            'wsdl' => $this->wsdl,
            'location' => $this->location ?: null,
            'sigla_sistema' => $this->sigla_sistema,
            'identificacao_servico' => $this->identificacao_servico,
            'id_unidade' => $this->id_unidade ?: null,
            'conteudo_charset' => $this->conteudo_charset,
            'timeout' => $this->timeout,
            'connection_timeout' => $this->connection_timeout,
            'verify_ssl' => $this->verify_ssl,
            'somente_leitura' => $this->somente_leitura,
            'operacoes' => $this->operacoes ?: null,
        ];
    }

    /**
     * Chaves de config('sei.sip.connections.{nome}').
     *
     * @return array<string, mixed>
     */
    public function paraConfigSip(): array
    {
        return [
            'wsdl' => $this->sip_wsdl,
            'chave_acesso' => $this->sip_chave_acesso,
            'id_sistema' => $this->sip_id_sistema ?: null,
            'timeout' => $this->timeout,
            'verify_ssl' => $this->verify_ssl,
        ];
    }
}
