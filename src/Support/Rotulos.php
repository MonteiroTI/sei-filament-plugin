<?php

namespace MonteiroTI\SeiFilament\Support;

use BackedEnum;
use MonteiroTi\Sei\Enum\AplicabilidadeSerie;
use MonteiroTi\Sei\Enum\EstadoBloco;
use MonteiroTi\Sei\Enum\EstadoPublicacao;
use MonteiroTi\Sei\Enum\Genero;
use MonteiroTi\Sei\Enum\MotivoPublicacao;
use MonteiroTi\Sei\Enum\Natureza;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Enum\OperacaoContato;
use MonteiroTi\Sei\Enum\TipoBloco;
use MonteiroTi\Sei\Enum\TipoDocumento;
use MonteiroTi\Sei\Enum\TipoVeiculo;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;

/**
 * Rótulo e cor (do Filament) dos enums do conector, que não têm label(): para selects, badges e textos.
 *
 * ```php
 * Select::make('nivel_acesso')->options(Rotulos::opcoes(NivelAcesso::class));
 * TextColumn::make('nivel')->badge()->formatStateUsing(fn ($state) => Rotulos::de($state))->color(fn ($state) => Rotulos::cor($state));
 * ```
 */
final class Rotulos
{
    public static function de(?BackedEnum $valor): ?string
    {
        return match (true) {
            $valor === null => null,
            $valor instanceof NivelAcesso => match ($valor) {
                NivelAcesso::Publico => 'Público',
                NivelAcesso::Restrito => 'Restrito',
                NivelAcesso::Sigiloso => 'Sigiloso',
            },
            $valor instanceof TipoDocumento => match ($valor) {
                TipoDocumento::Gerado => 'Gerado',
                TipoDocumento::Externo => 'Externo',
            },
            $valor instanceof TipoBloco => match ($valor) {
                TipoBloco::Assinatura => 'Assinatura',
                TipoBloco::Reuniao => 'Reunião',
                TipoBloco::Interno => 'Interno',
            },
            $valor instanceof EstadoBloco => match ($valor) {
                EstadoBloco::Aberto => 'Aberto',
                EstadoBloco::Disponibilizado => 'Disponibilizado',
                EstadoBloco::Recebido => 'Recebido',
                EstadoBloco::Retornado => 'Retornado',
                EstadoBloco::Concluido => 'Concluído',
            },
            $valor instanceof EstadoPublicacao => match ($valor) {
                EstadoPublicacao::Agendado => 'Agendado',
                EstadoPublicacao::Publicado => 'Publicado',
            },
            $valor instanceof MotivoPublicacao => match ($valor) {
                MotivoPublicacao::Publicacao => 'Publicação',
                MotivoPublicacao::Retificacao => 'Retificação',
                MotivoPublicacao::Republicacao => 'Republicação',
                MotivoPublicacao::Apostilamento => 'Apostilamento',
            },
            $valor instanceof TipoVeiculo => match ($valor) {
                TipoVeiculo::Interno => 'Interno',
                TipoVeiculo::Externo => 'Externo',
                TipoVeiculo::Modulo => 'Módulo',
            },
            $valor instanceof AplicabilidadeSerie => match ($valor) {
                AplicabilidadeSerie::Todos => 'Todos',
                AplicabilidadeSerie::Internos => 'Internos',
                AplicabilidadeSerie::Externos => 'Externos',
                AplicabilidadeSerie::Formularios => 'Formulários',
            },
            $valor instanceof Genero => match ($valor) {
                Genero::Feminino => 'Feminino',
                Genero::Masculino => 'Masculino',
            },
            $valor instanceof Natureza => match ($valor) {
                Natureza::PessoaFisica => 'Pessoa física',
                Natureza::PessoaJuridica => 'Pessoa jurídica',
            },
            $valor instanceof OperacaoContato => match ($valor) {
                OperacaoContato::Atualizar => 'Atualizar',
                OperacaoContato::Excluir => 'Excluir',
                OperacaoContato::Desativar => 'Desativar',
                OperacaoContato::Reativar => 'Reativar',
            },
            $valor instanceof StatusOperacao => match ($valor) {
                StatusOperacao::Executando => 'Executando',
                StatusOperacao::Concluida => 'Concluída',
                StatusOperacao::Falhou => 'Falhou',
                StatusOperacao::Incerta => 'Incerta',
                StatusOperacao::Descartada => 'Descartada',
            },
            default => (string) $valor->value,
        };
    }

    /** Cor do Filament (success, warning, danger, info, gray) para badges. */
    public static function cor(?BackedEnum $valor): string
    {
        return match ($valor) {
            NivelAcesso::Publico, EstadoBloco::Concluido, EstadoPublicacao::Publicado, StatusOperacao::Concluida => 'success',
            NivelAcesso::Restrito, EstadoBloco::Disponibilizado, EstadoBloco::Retornado, EstadoPublicacao::Agendado, StatusOperacao::Incerta => 'warning',
            NivelAcesso::Sigiloso, StatusOperacao::Falhou => 'danger',
            EstadoBloco::Aberto, EstadoBloco::Recebido, StatusOperacao::Executando => 'info',
            default => 'gray',
        };
    }

    /**
     * Opções de um enum do conector para Select/Radio: [valor => rótulo].
     *
     * @param  class-string<BackedEnum>  $enum
     * @return array<int|string, string>
     */
    public static function opcoes(string $enum): array
    {
        $opcoes = [];
        foreach ($enum::cases() as $caso) {
            $opcoes[$caso->value] = (string) self::de($caso);
        }

        return $opcoes;
    }
}
