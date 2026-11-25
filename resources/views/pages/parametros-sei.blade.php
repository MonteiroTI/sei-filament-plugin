@php
    use MonteiroTI\SeiFilament\Support\CatalogosDoSei;
    $conferencia = $this->conferencia;
    $situacoes = [
        'ok' => ['Confere', 'success'],
        'nao_encontrado' => ['Não encontrado ou não liberado', 'danger'],
        'nao_conferido' => ['Não conferido (SEI fora)', 'warning'],
        'sem_catalogo' => ['Grupo sem catálogo', 'gray'],
    ];
@endphp

<x-filament-panels::page>
    @include('sei-filament::partials.conexao-unidade', ['semUnidade' => true])

    @if ($this->registro === null)
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Esta conexão vem do config/sei.php: os parâmetros aparecem aqui, mas só são editados na tela quando a conexão está cadastrada em Conexões.
        </div>
    @endif

    @foreach ($conferencia['erros'] as $erro)
        <div class="text-sm text-danger-600 dark:text-danger-400">{{ $erro }}</div>
    @endforeach

    <x-filament::section>
        @if ($conferencia['linhas'] === [])
            <div class="text-sm text-gray-500 dark:text-gray-400">Nenhum parâmetro nesta conexão.</div>
        @else
            <div class="-mx-6 -my-6 overflow-x-auto">
                <table class="w-full table-auto divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            @foreach (['Parâmetro', 'ID', 'No SEI', 'Situação', 'Origem'] as $coluna)
                                <th class="px-3 py-3.5 text-start font-semibold text-gray-950 sm:first-of-type:ps-6 dark:text-white">{{ $coluna }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($conferencia['linhas'] as $linha)
                            <tr wire:key="parametro-{{ $linha['grupo'] }}-{{ $linha['chave'] }}">
                                <td class="px-3 py-3 font-mono text-gray-950 sm:first-of-type:ps-6 dark:text-white">{{ $linha['grupo'] }}.{{ $linha['chave'] }}</td>
                                <td class="px-3 py-3 font-mono text-gray-950 dark:text-white">{{ $linha['id'] }}</td>
                                <td class="px-3 py-3 text-gray-950 dark:text-white">{{ $linha['nome'] ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <x-filament::badge :color="$situacoes[$linha['situacao']][1]" class="inline-flex">{{ $situacoes[$linha['situacao']][0] }}</x-filament::badge>
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $linha['origem'] === 'banco' ? 'Banco' : 'config/sei.php' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
