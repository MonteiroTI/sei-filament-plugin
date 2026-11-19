@php
    use MonteiroTI\SeiFilament\Support\Rotulos;
    /** @var \MonteiroTi\Sei\Dto\RetornoConsultaProcedimento|null $processo */
    /** @var list<\MonteiroTi\Sei\Dto\Andamento> $andamentos */
    $data = fn (?DateTimeInterface $d) => $d?->format('d/m/Y H:i');
@endphp

<div class="grid gap-y-6 text-sm">
    @if ($erro !== null)
        <div class="rounded-lg bg-danger-50 p-4 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400" style="white-space: pre-line">{{ $erro }}</div>
    @else
        <dl class="grid gap-y-3">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Tipo</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $processo->tipoProcedimento?->nome ?? '—' }}</dd>
            </div>

            @if (filled($processo->especificacao))
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Especificação</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $processo->especificacao }}</dd>
                </div>
            @endif

            <div class="flex flex-wrap gap-x-8 gap-y-3">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Nível de acesso</dt>
                    <dd>
                        @if ($processo->nivelAcessoGlobal)
                            <x-filament::badge :color="Rotulos::cor($processo->nivelAcessoGlobal)" class="inline-flex">
                                {{ Rotulos::de($processo->nivelAcessoGlobal) }}
                            </x-filament::badge>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Situação</dt>
                    <dd>
                        <x-filament::badge :color="$processo->aberto() ? 'info' : 'gray'" class="inline-flex">
                            {{ $processo->aberto() ? 'Aberto' : 'Concluído' }}
                        </x-filament::badge>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Autuação</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $processo->dataAutuacao?->format('d/m/Y') ?? '—' }}</dd>
                </div>
            </div>

            @if ($processo->unidadesProcedimentoAberto !== [])
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Aberto em</dt>
                    <dd class="text-gray-950 dark:text-white">
                        @foreach ($processo->unidadesProcedimentoAberto as $aberto)
                            <div>
                                {{ $aberto->unidade?->sigla ?? $aberto->unidade?->idUnidade }}
                                @if ($aberto->usuarioAtribuicao)
                                    <span class="text-gray-500 dark:text-gray-400">· atribuído a {{ $aberto->usuarioAtribuicao->sigla ?? $aberto->usuarioAtribuicao->nome }}</span>
                                @endif
                            </div>
                        @endforeach
                    </dd>
                </div>
            @endif

            @if ($processo->interessados !== [])
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Interessados</dt>
                    <dd class="text-gray-950 dark:text-white">
                        {{ collect($processo->interessados)->map(fn ($i) => $i->nome ?? $i->sigla)->filter()->join(', ') }}
                    </dd>
                </div>
            @endif

            @if ($processo->procedimentosRelacionados !== [] || $processo->procedimentosAnexados !== [])
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Relacionados e anexados</dt>
                    <dd class="text-gray-950 dark:text-white">
                        {{ collect([...$processo->procedimentosRelacionados, ...$processo->procedimentosAnexados])->map(fn ($p) => $p->procedimentoFormatado)->filter()->join(', ') }}
                    </dd>
                </div>
            @endif
        </dl>

        @if (filled($processo->linkAcesso))
            <div>
                <x-filament::button tag="a" :href="$processo->linkAcesso" target="_blank" rel="noopener" color="gray" icon="heroicon-m-arrow-top-right-on-square" size="sm">
                    Abrir no SEI
                </x-filament::button>
            </div>
        @endif

        <div class="grid gap-y-3 border-t border-gray-200 pt-4 dark:border-white/10">
            <div class="font-medium text-gray-950 dark:text-white">Andamentos</div>

            @forelse ($andamentos as $andamento)
                <div>
                    <div class="text-gray-500 dark:text-gray-400">
                        {{ $data($andamento->dataHora) }}
                        @if ($andamento->unidade?->sigla) · {{ $andamento->unidade->sigla }} @endif
                        @if ($andamento->usuario?->sigla) · {{ $andamento->usuario->sigla }} @endif
                    </div>
                    {{-- A descrição do SEI vem com HTML (links para documentos): só o texto, escapado. --}}
                    <div class="text-gray-950 dark:text-white">{{ html_entity_decode(strip_tags((string) $andamento->descricao), ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</div>
                </div>
            @empty
                <div class="text-gray-500 dark:text-gray-400">O SEI não devolveu andamentos.</div>
            @endforelse
        </div>
    @endif
</div>
