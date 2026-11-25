@php
    use MonteiroTI\SeiFilament\Support\Rotulos;
    $consulta = $this->consulta;
    /** @var \MonteiroTi\Sei\Dto\Bloco|null $bloco */
    $bloco = $consulta['bloco'];
@endphp

<x-filament-panels::page>
    @include('sei-filament::partials.conexao-unidade')

    <form wire:submit="consultar" class="flex items-end gap-3">
        <label class="grid flex-1 gap-y-2">
            <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Número do bloco</span>
            <x-filament::input.wrapper>
                <x-filament::input type="text" wire:model="bloco" placeholder="1234" />
            </x-filament::input.wrapper>
        </label>
        <x-filament::button type="submit" icon="heroicon-m-magnifying-glass">Consultar</x-filament::button>
    </form>

    @if ($consulta['erro'] !== null)
        <x-filament::section>
            <div class="text-sm text-danger-600 dark:text-danger-400" style="white-space: pre-line">{{ $consulta['erro'] }}</div>
        </x-filament::section>
    @elseif ($bloco !== null)
        <x-filament::section>
            <x-slot name="heading">Bloco {{ $bloco->idBloco }}</x-slot>
            <x-slot name="description">{{ $bloco->descricao }}</x-slot>

            <dl class="grid gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Tipo</dt>
                    <dd class="text-gray-950 dark:text-white">{{ Rotulos::de($bloco->tipo) ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Situação</dt>
                    <dd>
                        @if ($bloco->estado)
                            <x-filament::badge :color="Rotulos::cor($bloco->estado)" class="inline-flex">{{ Rotulos::de($bloco->estado) }}</x-filament::badge>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Unidade</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $bloco->unidade?->sigla ?? '—' }} @if ($bloco->usuario?->sigla) · {{ $bloco->usuario->sigla }} @endif</dd>
                </div>
                @if ($bloco->unidadesDisponibilizacao !== [])
                    <div class="sm:col-span-3">
                        <dt class="text-gray-500 dark:text-gray-400">Disponibilizado para</dt>
                        <dd class="text-gray-950 dark:text-white">{{ collect($bloco->unidadesDisponibilizacao)->map(fn ($u) => $u->sigla ?? $u->idUnidade)->join(', ') }}</dd>
                    </div>
                @endif
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Protocolos ({{ count($bloco->protocolos) }})</x-slot>

            @if ($bloco->protocolos === [])
                <div class="text-sm text-gray-500 dark:text-gray-400">O bloco está vazio.</div>
            @else
                <div class="-mx-6 -my-6 overflow-x-auto">
                    <table class="w-full table-auto divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-3.5 text-start font-semibold text-gray-950 sm:first-of-type:ps-6 dark:text-white">Protocolo</th>
                                <th class="px-3 py-3.5 text-start font-semibold text-gray-950 dark:text-white">Identificação</th>
                                <th class="px-3 py-3.5 text-start font-semibold text-gray-950 dark:text-white">Assinaturas</th>
                                <th class="px-3 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach ($bloco->protocolos as $protocolo)
                                <tr wire:key="protocolo-{{ $protocolo->protocoloFormatado }}">
                                    <td class="px-3 py-3 font-mono text-gray-950 sm:first-of-type:ps-6 dark:text-white">{{ $protocolo->protocoloFormatado }}</td>
                                    <td class="px-3 py-3 text-gray-950 dark:text-white">{{ $protocolo->identificacao }}</td>
                                    <td class="px-3 py-3 text-gray-950 dark:text-white">
                                        @forelse ($protocolo->assinaturas as $assinatura)
                                            <div>{{ $assinatura->nome }} <span class="text-gray-500 dark:text-gray-400">{{ $assinatura->cargoFuncao }} · {{ $assinatura->dataHora?->format('d/m/Y H:i') }}</span></div>
                                        @empty
                                            <span class="text-gray-500 dark:text-gray-400">Sem assinatura</span>
                                        @endforelse
                                    </td>
                                    <td class="px-3 py-3 text-end">
                                        <x-filament::link
                                            tag="button"
                                            color="danger"
                                            wire:click="retirar(@js($protocolo->protocoloFormatado))"
                                            wire:confirm="Retirar {{ $protocolo->protocoloFormatado }} do bloco?"
                                        >Retirar</x-filament::link>
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
