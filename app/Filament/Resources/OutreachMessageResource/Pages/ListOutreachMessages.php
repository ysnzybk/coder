<?php

namespace App\Filament\Resources\OutreachMessageResource\Pages;

use App\Filament\Resources\OutreachMessageResource;
use App\Models\OutreachMessage;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOutreachMessages extends ListRecords
{
    protected static string $resource = OutreachMessageResource::class;

    public function getTabs(): array
    {
        return [
            'draft' => Tab::make('Onay bekleyen')
                ->badge(OutreachMessage::where('status', 'draft')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')),
            'to_send' => Tab::make('WhatsApp gönderilecek')
                ->badge(OutreachMessage::where('channel', 'whatsapp')->where('status', 'approved')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('channel', 'whatsapp')->where('status', 'approved')),
            'queued' => Tab::make('E-posta kuyruğu')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'queued')),
            'sent' => Tab::make('Gönderilen')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'sent')),
            'all' => Tab::make('Tümü'),
        ];
    }
}
