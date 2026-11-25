@php($resultado = $this->resultado)

<x-filament-panels::page>
    @include('sei-filament::partials.conexao-unidade')

    <form wire:submit="buscar" class="grid gap-4 md:grid-cols-5">
        @foreach (['nome' => 'Nome', 'sigla' => 'Sigla', 'cpf' => 'CPF', 'cnpj' => 'CNPJ', 'matricula' => 'Matrícula'] as $campo => $rotulo)
            <label class="grid gap-y-2">
                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">{{ $rotulo }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="filtro.{{ $campo }}" />
                </x-filament::input.wrapper>
            </label>
        @endforeach
        <div class="md:col-span-5">
            <x-filament::button type="submit" icon="heroicon-m-magnifying-glass">Buscar contatos</x-filament::button>
        </div>
    </form>

    @if ($resultado['erro'] !== null)
        <x-filament::section>
            <div class="text-sm text-danger-600 dark:text-danger-400" style="white-space: pre-line">{{ $resultado['erro'] }}</div>
        </x-filament::section>
    @elseif ($this->buscou)
        <x-filament::section>
            <x-slot name="heading">Página {{ $this->pagina }}</x-slot>
            <x-slot name="headerEnd">
                <div class="flex gap-2">
                    @if ($this->pagina > 1)
                        <x-filament::button size="sm" color="gray" wire:click="irParaPagina({{ $this->pagina - 1 }})">Anterior</x-filament::button>
                    @endif
                    @if (count($resultado['contatos']) === \MonteiroTI\SeiFilament\Pages\ContatosSei::POR_PAGINA)
                        <x-filament::button size="sm" color="gray" wire:click="irParaPagina({{ $this->pagina + 1 }})">Próxima</x-filament::button>
                    @endif
                </div>
            </x-slot>

            @if ($resultado['contatos'] === [])
                <div class="text-sm text-gray-500 dark:text-gray-400">Nenhum contato.</div>
            @else
                <div class="-mx-6 -my-6 overflow-x-auto">
                    <table class="w-full table-auto divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                @foreach (['ID', 'Nome', 'Sigla', 'Tipo', 'CPF/CNPJ', 'E-mail', 'Situação', ''] as $coluna)
                                    <th class="px-3 py-3.5 text-start font-semibold text-gray-950 sm:first-of-type:ps-6 dark:text-white">{{ $coluna }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach ($resultado['contatos'] as $contato)
                                <tr wire:key="contato-{{ $contato->idContato }}">
                                    <td class="px-3 py-3 font-mono text-gray-950 sm:first-of-type:ps-6 dark:text-white">{{ $contato->idContato }}</td>
                                    <td class="px-3 py-3 text-gray-950 dark:text-white">{{ $contato->nome }}</td>
                                    <td class="px-3 py-3 text-gray-950 dark:text-white">{{ $contato->sigla }}</td>
                                    <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $contato->nomeTipoContato }}</td>
                                    <td class="px-3 py-3 font-mono text-gray-950 dark:text-white">{{ $contato->cpf ?? $contato->cnpj }}</td>
                                    <td class="px-3 py-3 text-gray-950 dark:text-white">{{ $contato->email }}</td>
                                    <td class="px-3 py-3">
                                        <x-filament::badge :color="$contato->ativo === false ? 'gray' : 'success'" class="inline-flex">{{ $contato->ativo === false ? 'Desativado' : 'Ativo' }}</x-filament::badge>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-end">
                                        {{ ($this->editarAction)(['id' => $contato->idContato]) }}
                                        @if ($contato->ativo === false)
                                            {{ ($this->reativarAction)(['id' => $contato->idContato]) }}
                                        @else
                                            {{ ($this->desativarAction)(['id' => $contato->idContato]) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif

</x-filament-panels::page>
