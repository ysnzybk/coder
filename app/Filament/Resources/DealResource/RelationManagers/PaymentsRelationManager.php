<?php

namespace App\Filament\Resources\DealResource\RelationManagers;

use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Ödemeler';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')->label('Tür')->options(Payment::TYPES)->required(),
            Forms\Components\TextInput::make('amount')->label('Tutar (TL)')->numeric()->required(),
            Forms\Components\TextInput::make('period')->label('Dönem (YYYY-AA)')->placeholder('2026-12'),
            Forms\Components\DatePicker::make('due_date')->label('Vade')->required(),
            Forms\Components\DateTimePicker::make('paid_at')->label('Ödendi'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('due_date', 'desc')->columns([
            Tables\Columns\TextColumn::make('type')->label('Tür')->formatStateUsing(fn ($s) => Payment::TYPES[$s] ?? $s),
            Tables\Columns\TextColumn::make('period')->label('Dönem')->placeholder('—'),
            Tables\Columns\TextColumn::make('amount')->label('Tutar')->money('TRY', locale: 'tr'),
            Tables\Columns\TextColumn::make('due_date')->label('Vade')->date('d.m.Y'),
            Tables\Columns\TextColumn::make('paid_at')->label('Ödendi')->dateTime('d.m.Y')->placeholder('Bekliyor')
                ->color(fn ($state, Payment $r) => ! $state && $r->due_date->isPast() ? 'danger' : null),
        ])->headerActions([
            Tables\Actions\CreateAction::make()->label('Ödeme ekle'),
        ])->actions([
            Tables\Actions\Action::make('paid')->label('Ödendi')->icon('heroicon-o-check')->color('success')
                ->visible(fn (Payment $r) => ! $r->paid_at)
                ->action(fn (Payment $r) => $r->update(['paid_at' => now()])),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }
}
