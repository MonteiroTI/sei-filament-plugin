<x-filament-panels::page>
    @include('sei-filament::partials.conexao-unidade')

    <x-filament::tabs>
        @foreach (\MonteiroTI\SeiFilament\Support\CatalogosDoSei::CATALOGOS as $chave => $catalogo)
            <x-filament::tabs.item
                :active="$this->catalogo === $chave"
                wire:click="escolherCatalogo('{{ $chave }}')"
            >
                {{ $catalogo['rotulo'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @php($resultado = $this->resultado)

    <x-filament::section>
        <x-slot name="heading">
            {{ \MonteiroTI\SeiFilament\Support\CatalogosDoSei::CATALOGOS[$this->catalogo]['rotulo'] }}
        </x-slot>

        <x-slot name="description">
            @if ($resultado['erro'] === null)
                {{ $resultado['total'] }} registro(s){{ $resultado['total'] > count($resultado['linhas']) ? ', mostrando '.count($resultado['linhas']).': refine a busca' : '' }}.
            @endif
        </x-slot>

        <x-slot name="headerEnd">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.400ms="busca"
                    placeholder="Buscar por ID, sigla ou nome"
                />
            </x-filament::input.wrapper>
        </x-slot>

        @if ($resultado['erro'] !== null)
            <div class="text-sm text-danger-600 dark:text-danger-400" style="white-space: pre-line">{{ $resultado['erro'] }}</div>
        @elseif ($resultado['linhas'] === [])
            <div class="text-sm text-gray-500 dark:text-gray-400">Nenhum registro.</div>
        @else
            <div class="-mx-6 -my-6 overflow-x-auto">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            @foreach ($this->colunas() as $rotulo)
                                <th class="px-3 py-3.5 text-start font-semibold text-gray-950 sm:first-of-type:ps-6 dark:text-white">{{ $rotulo }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                        @foreach ($resultado['linhas'] as $linha)
                            <tr wire:key="{{ $this->catalogo }}-{{ $loop->index }}-{{ reset($linha) }}">
                                @foreach ($linha as $coluna => $valor)
                                    <td class="px-3 py-3 text-gray-950 sm:first-of-type:ps-6 dark:text-white">
                                        @if ($loop->first)
                                            <span class="font-mono" x-data x-on:click="window.navigator.clipboard?.writeText(@js($valor)); $tooltip('Copiado', { timeout: 1500 })" style="cursor: copy">{{ $valor }}</span>
                                        @else
                                            {{ $valor }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
