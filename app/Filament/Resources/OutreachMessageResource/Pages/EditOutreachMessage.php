<?php

namespace App\Filament\Resources\OutreachMessageResource\Pages;

use App\Filament\Resources\OutreachMessageResource;
use App\Models\OutreachMessage;
use App\Services\Outreach\OutreachService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOutreachMessage extends EditRecord
{
    protected static string $resource = OutreachMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Kaydet ve onayla')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->status === OutreachMessage::STATUS_DRAFT)
                ->action(function () {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    app(OutreachService::class)->approve($this->record->refresh());
                    Notification::make()->title('Onaylandı')->success()->send();
                    $this->redirect(OutreachMessageResource::getUrl('index'));
                }),
            Actions\Action::make('whatsapp')
                ->label('WhatsApp\'ta aç')
                ->color('success')
                ->visible(fn () => $this->record->channel === 'whatsapp' && in_array($this->record->status, ['approved', 'sent'], true))
                ->url(fn () => route('admin.whatsapp', $this->record), shouldOpenInNewTab: true),
            Actions\Action::make('reject')
                ->label('Reddet')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => in_array($this->record->status, ['draft', 'approved', 'queued'], true))
                ->action(function () {
                    app(OutreachService::class)->reject($this->record);
                    $this->redirect(OutreachMessageResource::getUrl('index'));
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return $this->record->status === OutreachMessage::STATUS_DRAFT ? parent::getFormActions() : [];
    }
}
