<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\District;
use App\Models\Province;
use App\Services\Places\FilePlacesImporter;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ImportPlaces extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Ayarlar';

    protected static ?string $navigationLabel = 'Dosyadan içe aktar';

    protected static ?string $title = 'Dosyadan içe aktar (Overture / CSV)';

    protected static string $view = 'filament.pages.import-places';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['min_confidence' => config('leadgen.file_import.min_confidence')]);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Placeholder::make('help')->hiddenLabel()->content(new HtmlString(
                'Kendi bilgisayarınızda DuckDB ile ürettiğiniz CSV dosyasını yükleyin (adımlar: <code>docs/OVERTURE.md</code>). '
                .'Aktarılan firmalar <b>Doğrulama bekliyor</b> durumuna düşer; "web sitesi yok" bilgisini siz teyit edersiniz.'
            )),
            Forms\Components\Select::make('province_id')->label('İl')->required()->searchable()->live()
                ->options(fn () => Province::orderBy('name')->pluck('name', 'id'))
                ->afterStateUpdated(fn (Forms\Set $set) => $set('district_id', null)),
            Forms\Components\Select::make('district_id')->label('İlçe (opsiyonel)')->searchable()
                ->options(fn (Forms\Get $get) => District::where('province_id', $get('province_id'))->orderBy('name')->pluck('name', 'id')),
            Forms\Components\Select::make('category_id')->label('Sektör (boşsa kaynak kategorisi eşlenir)')
                ->options(fn () => Category::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            Forms\Components\TextInput::make('min_confidence')->label('Minimum güven puanı (0-1)')->numeric()->minValue(0)->maxValue(1)->step(0.05),
            Forms\Components\FileUpload::make('file')->label('CSV dosyası')->required()
                ->disk('local')->directory('imports')->maxSize(20 * 1024)
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv']),
        ]);
    }

    public function import(): void
    {
        $data = $this->form->getState();
        $path = Storage::disk('local')->path($data['file']);

        try {
            $stats = app(FilePlacesImporter::class)->import(
                $path,
                Province::findOrFail($data['province_id']),
                filled($data['district_id'] ?? null) ? District::find($data['district_id']) : null,
                filled($data['category_id'] ?? null) ? Category::find($data['category_id']) : null,
                filled($data['min_confidence'] ?? null) ? (float) $data['min_confidence'] : null,
            );
        } catch (\Throwable $e) {
            Notification::make()->title('İçe aktarma başarısız')->body($e->getMessage())->danger()->send();

            return;
        } finally {
            Storage::disk('local')->delete($data['file']);
        }

        Notification::make()
            ->title("{$stats['imported']} firma aktarıldı (doğrulama bekliyor)")
            ->body("Satır: {$stats['rows']} · Zaten kayıtlı: {$stats['duplicate']} · Düşük güven: {$stats['low_confidence']} · İlçe eşleşmedi: {$stats['no_district']} · Sektör eşleşmedi: {$stats['no_category']} · Elenen: {$stats['disqualified']}")
            ->success()
            ->persistent()
            ->send();

        $this->form->fill(['min_confidence' => config('leadgen.file_import.min_confidence')]);
    }
}
