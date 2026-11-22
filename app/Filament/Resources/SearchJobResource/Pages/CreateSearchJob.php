<?php

namespace App\Filament\Resources\SearchJobResource\Pages;

use App\Filament\Resources\SearchJobResource;
use App\Jobs\RunSearchJob;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSearchJob extends CreateRecord
{
    protected static string $resource = SearchJobResource::class;

    protected static bool $canCreateAnother = false;

    protected function afterCreate(): void
    {
        RunSearchJob::dispatch($this->record->id);

        Notification::make()
            ->title('Arama kuyruğa alındı')
            ->body('Sonuçlar birkaç dakika içinde "Firmalar" ekranına düşer. Sitesi olmayan ve cep telefonu/e-postası olanlar için siteler otomatik üretilir.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
