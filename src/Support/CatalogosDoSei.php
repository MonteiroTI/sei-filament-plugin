<?php

namespace MonteiroTI\SeiFilament\Support;

use BackedEnum;
use MonteiroTi\Sei\Api\Catalogos;
use MonteiroTi\Sei\Dto\ArquivoExtensao;
use MonteiroTi\Sei\Dto\HipoteseLegal;
use MonteiroTi\Sei\Dto\Marcador;
use MonteiroTi\Sei\Dto\Serie;
use MonteiroTi\Sei\Dto\TipoConferencia;
use MonteiroTi\Sei\Dto\TipoPrioridade;
use MonteiroTi\Sei\Dto\TipoProcedimento;
use MonteiroTi\Sei\Dto\Unidade;
use MonteiroTi\Sei\Dto\Usuario;

/**
 * Os catálogos do SEI que a tela mostra e os selects usam: operação, colunas e [id => rótulo] de cada um.
 */
final class CatalogosDoSei
{
    /**
     * @var array<string, array{rotulo: string, operacao: string, id: string, colunas: array<string, string>}>
     */
    public const CATALOGOS = [
        'unidades' => [
            'rotulo' => 'Unidades',
            'operacao' => 'listarUnidades',
            'id' => 'idUnidade',
            'colunas' => ['idUnidade' => 'ID', 'sigla' => 'Sigla', 'descricao' => 'Descrição', 'protocolo' => 'Protocolo', 'arquivamento' => 'Arquivamento', 'ouvidoria' => 'Ouvidoria'],
        ],
        'series' => [
            'rotulo' => 'Tipos de documento',
            'operacao' => 'listarSeries',
            'id' => 'idSerie',
            'colunas' => ['idSerie' => 'ID', 'nome' => 'Nome', 'aplicabilidade' => 'Aplicabilidade'],
        ],
        'tipos_processo' => [
            'rotulo' => 'Tipos de processo',
            'operacao' => 'listarTiposProcedimento',
            'id' => 'idTipoProcedimento',
            'colunas' => ['idTipoProcedimento' => 'ID', 'nome' => 'Nome'],
        ],
        'hipoteses_legais' => [
            'rotulo' => 'Hipóteses legais',
            'operacao' => 'listarHipotesesLegais',
            'id' => 'idHipoteseLegal',
            'colunas' => ['idHipoteseLegal' => 'ID', 'nome' => 'Nome', 'baseLegal' => 'Base legal', 'nivelAcesso' => 'Nível de acesso'],
        ],
        'marcadores' => [
            'rotulo' => 'Marcadores',
            'operacao' => 'listarMarcadoresUnidade',
            'id' => 'idMarcador',
            'colunas' => ['idMarcador' => 'ID', 'nome' => 'Nome', 'icone' => 'Ícone', 'ativo' => 'Ativo'],
        ],
        'usuarios' => [
            'rotulo' => 'Usuários',
            'operacao' => 'listarUsuarios',
            'id' => 'idUsuario',
            'colunas' => ['idUsuario' => 'ID', 'sigla' => 'Sigla', 'nome' => 'Nome'],
        ],
        'tipos_conferencia' => [
            'rotulo' => 'Tipos de conferência',
            'operacao' => 'listarTiposConferencia',
            'id' => 'idTipoConferencia',
            'colunas' => ['idTipoConferencia' => 'ID', 'descricao' => 'Descrição'],
        ],
        'tipos_prioridade' => [
            'rotulo' => 'Tipos de prioridade',
            'operacao' => 'listarTiposPrioridade',
            'id' => 'idTipoPrioridade',
            'colunas' => ['idTipoPrioridade' => 'ID', 'nome' => 'Nome'],
        ],
        'extensoes' => [
            'rotulo' => 'Extensões permitidas',
            'operacao' => 'listarExtensoesPermitidas',
            'id' => 'idArquivoExtensao',
            'colunas' => ['idArquivoExtensao' => 'ID', 'extensao' => 'Extensão', 'descricao' => 'Descrição'],
        ],
    ];

    /**
     * Registros do catálogo como vieram do conector. $unidade filtra os catálogos em que a unidade é opcional
     * (séries, tipos de processo, de conferência e de prioridade); nos outros vale a unidade do cliente (naUnidade).
     *
     * @return list<Unidade|Serie|TipoProcedimento|HipoteseLegal|Marcador|Usuario|TipoConferencia|TipoPrioridade|ArquivoExtensao>
     */
    public static function registros(Catalogos $catalogos, string $catalogo, ?string $unidade = null): array
    {
        return match ($catalogo) {
            'unidades' => $catalogos->unidades(),
            'series' => $catalogos->series(idUnidade: $unidade),
            'tipos_processo' => $catalogos->tiposProcedimento(idUnidade: $unidade),
            'hipoteses_legais' => $catalogos->hipotesesLegais(),
            'marcadores' => $catalogos->marcadoresUnidade(),
            'usuarios' => $catalogos->usuarios(),
            'tipos_conferencia' => $catalogos->tiposConferencia($unidade),
            'tipos_prioridade' => $catalogos->tiposPrioridade($unidade),
            'extensoes' => $catalogos->extensoesPermitidas(),
            default => throw new \InvalidArgumentException("Catálogo do SEI desconhecido: {$catalogo}."),
        };
    }

    /**
     * Linhas para a tabela da tela, com os valores já em texto.
     *
     * @return list<array<string, string>>
     */
    public static function linhas(Catalogos $catalogos, string $catalogo, ?string $unidade = null): array
    {
        $colunas = array_keys(self::CATALOGOS[$catalogo]['colunas']);

        return array_map(function (object $registro) use ($colunas) {
            $linha = [];
            foreach ($colunas as $coluna) {
                $linha[$coluna] = self::texto($registro->{$coluna} ?? null);
            }

            return $linha;
        }, self::registros($catalogos, $catalogo, $unidade));
    }

    /**
     * Opções para Select: [id => rótulo]. Unidades e usuários pela sigla e nome; o resto pelo nome.
     *
     * @return array<string, string>
     */
    public static function opcoes(Catalogos $catalogos, string $catalogo, ?string $unidade = null): array
    {
        $opcoes = [];
        foreach (self::registros($catalogos, $catalogo, $unidade) as $registro) {
            $id = $registro->{self::CATALOGOS[$catalogo]['id']};
            if ($id === null) {
                continue;
            }

            $opcoes[$id] = match (true) {
                $registro instanceof Unidade => trim(($registro->sigla ?? '').' · '.($registro->descricao ?? ''), ' ·'),
                $registro instanceof Usuario => trim(($registro->nome ?? '').' ('.($registro->sigla ?? '').')', ' ()'),
                $registro instanceof TipoConferencia, $registro instanceof ArquivoExtensao => (string) ($registro->descricao ?? $registro->extensao ?? $id),
                default => (string) ($registro->nome ?? $id),
            } ?: (string) $id;
        }

        asort($opcoes, SORT_NATURAL | SORT_FLAG_CASE);

        return $opcoes;
    }

    private static function texto(mixed $valor): string
    {
        return match (true) {
            $valor === null => '',
            is_bool($valor) => $valor ? 'Sim' : 'Não',
            $valor instanceof BackedEnum => (string) Rotulos::de($valor),
            is_scalar($valor) => (string) $valor,
            default => '',
        };
    }
}
