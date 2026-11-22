<?php

namespace App\Filament\Resources\DealResource\Pages;

use App\Filament\Resources\DealResource;
use Filament\Resources\Pages\EditRecord;

class EditDeal extends EditRecord
{
    protected static string $resource = DealResource::class;

    protected function afterSave(): void
    {
        $domain = mb_strtolower(trim((string) ($this->data['custom_domain'] ?? ''))) ?: null;
        $domain = $domain ? preg_replace('/^(https?:\/\/)?(www\.)?/', '', rtrim($domain, '/')) : null;

        $this->record->business?->site?->update(['custom_domain' => $domain]);
    }
}
