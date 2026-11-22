<?php

namespace App\Filament\Resources\SearchJobResource\Pages;

use App\Filament\Resources\SearchJobResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSearchJobs extends ListRecords
{
    protected static string $resource = SearchJobResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Yeni arama')];
    }
}
