<?php

namespace MonteiroTI\SeiFilament\Notifications;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Aviso no sino do Filament (notificação de banco) sobre um processo ou documento do SEI.
 */
class AvisoSeiNotification extends Notification
{
    /**
     * @param  string  $tipo  chave em AvisosDoSei::TIPOS
     * @param  string|null  $url  tela do processo no painel; null, sem botão
     * @param  string|null  $linkSei  link de acesso no SEI
     */
    public function __construct(
        public readonly string $tipo,
        public readonly string $titulo,
        public readonly string $corpo,
        public readonly string $cor = 'info',
        public readonly string $icone = 'heroicon-o-folder',
        public readonly ?string $url = null,
        public readonly ?string $linkSei = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->titulo)
            ->body($this->corpo)
            ->icon($this->icone)
            ->iconColor($this->cor)
            ->actions(array_values(array_filter([
                $this->url !== null ? Action::make('ver')->label('Ver o processo')->url($this->url)->markAsRead() : null,
                $this->linkSei !== null ? Action::make('sei')->label('Abrir no SEI')->url($this->linkSei, shouldOpenInNewTab: true)->markAsRead() : null,
            ])))
            ->getDatabaseMessage();
    }
}
