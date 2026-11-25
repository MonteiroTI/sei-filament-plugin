@php($resultado = $this->resultado)

<x-filament-panels::page>
    @if ($this->conexoes === [])
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Nenhuma conexão com o SIP. Preencha a seção "SIP" de uma conexão do SEI (ou sei.sip no config).</div>
        </x-filament::section>
    @else
        <form wire:submit="buscar" class="grid gap-4 md:grid-cols-5">
            <label class="grid gap-y-2">
                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Conexão SIP</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="conexao">
                        @foreach ($this->conexoes as $nome => $rotulo)
                            <option value="{{ $nome }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            @foreach (['id_usuario' => 'Usuário (ID no SIP)', 'id_origem_usuario' => 'Usuário (ID de origem)', 'id_unidade' => 'Unidade (ID)', 'id_perfil' => 'Perfil (ID)'] as $campo => $rotulo)
                <label class="grid gap-y-2">
                    <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">{{ $rotulo }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="filtro.{{ $campo }}" />
                    </x-filament::input.wrapper>
                </label>
            @endforeach
            <div class="md:col-span-5">
                <x-filament::button type="submit" icon="heroicon-m-magnifying-glass">Buscar permissões</x-filament::button>
            </div>
        </form>

        @if ($resultado['erro'] !== null)
            <x-filament::section>
                <div class="text-sm text-danger-600 dark:text-danger-400" style="white-space: pre-line">{{ $resultado['erro'] }}</div>
            </x-filament::section>
        @elseif ($this->buscou)
            <x-filament::section>
                <x-slot name="heading">Permissões ({{ count($resultado['permissoes']) }})</x-slot>

                @if ($resultado['permissoes'] === [])
                    <div class="text-sm text-gray-500 dark:text-gray-400">Nenhuma permissão com esse filtro.</div>
                @else
                    <div class="-mx-6 -my-6 overflow-x-auto">
                        <table class="w-full table-auto divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    @foreach (['Usuário', 'Unidade', 'Perfil', 'Vigência', 'Subunidades', ''] as $coluna)
                                        <th class="px-3 py-3.5 text-start font-semibold text-gray-950 sm:first-of-type:ps-6 dark:text-white">{{ $coluna }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @foreach ($resultado['permissoes'] as $i => $p)
                                    <tr wire:key="permissao-{{ $i }}-{{ $p->idUsuario }}-{{ $p->idUnidade }}-{{ $p->idPerfil }}">
                                        <td class="px-3 py-3 text-gray-950 sm:first-of-type:ps-6 dark:text-white">{{ $p->idUsuario }} <span class="text-gray-500 dark:text-gray-400">{{ $p->idOrigemUsuario ? '· origem '.$p->idOrigemUsuario : '' }}</span></td>
                                        <td class="px-3 py-3 text-gray-950 dark:text-white">{{ $p->idUnidade }}</td>
                                        <td class="px-3 py-3 text-gray-950 dark:text-white">{{ $p->idPerfil }}</td>
                                        <td class="px-3 py-3 text-gray-950 dark:text-white">{{ $p->dataInicial?->format('d/m/Y') ?? '—' }} a {{ $p->dataFinal?->format('d/m/Y') ?? 'sem fim' }}</td>
                                        <td class="px-3 py-3 text-gray-950 dark:text-white">{{ $p->subunidades ? 'Sim' : 'Não' }}</td>
                                        <td class="px-3 py-3 text-end">
                                            <x-filament::link tag="button" color="danger" wire:click="retirar({{ $i }})" wire:confirm="Retirar esta permissão no SIP?">Retirar</x-filament::link>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
