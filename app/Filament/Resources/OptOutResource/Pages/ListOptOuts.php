<?php

namespace App\Filament\Resources\OptOutResource\Pages;

use App\Filament\Resources\OptOutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOptOuts extends ListRecords
{
    protected static string $resource = OptOutResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
