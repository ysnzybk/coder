<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\District;
use App\Models\Province;
use App\Services\Places\FilePlacesImporter;
use App\Support\TurkishText;
use Illuminate\Console\Command;

class ImportPlacesFile extends Command
{
    protected $signature = 'places:import-file
        {dosya : CSV dosyası (id,name,address,phone,website,email,social,lat,lng,category,confidence,status)}
        {--il= : İl adı veya plaka kodu (zorunlu)}
        {--ilce= : Yalnızca bu ilçe}
        {--kategori= : Sektör slug\'ı (verilmezse kaynak kategorisi config ile eşlenir)}
        {--min-confidence= : Bu güven puanının altındaki satırlar atlanır}';

    protected $description = 'CSV dosyasından (ör. Overture Maps) firma içe aktarır; kayıtlar "Doğrulama bekliyor" olur';

    public function handle(FilePlacesImporter $importer): int
    {
        $il = (string) $this->option('il');
        $province = ctype_digit($il)
            ? Province::where('plate_code', (int) $il)->first()
            : Province::all()->first(fn (Province $p) => TurkishText::fold($p->name) === TurkishText::fold($il));

        if (! $province) {
            $this->error('İl bulunamadı. --il="İstanbul" veya --il=34 şeklinde verin.');

            return self::INVALID;
        }

        $district = null;
        if ($name = $this->option('ilce')) {
            $district = District::where('province_id', $province->id)->get()
                ->first(fn (District $d) => TurkishText::fold($d->name) === TurkishText::fold($name));
            if (! $district) {
                $this->error("İlçe bulunamadı: {$name}");

                return self::INVALID;
            }
        }

        $category = null;
        if ($slug = $this->option('kategori')) {
            $category = Category::where('slug', $slug)->first();
            if (! $category) {
                $this->error("Sektör bulunamadı: {$slug} (Ayarlar → Sektörler)");

                return self::INVALID;
            }
        }

        try {
            $stats = $importer->import(
                $this->argument('dosya'), $province, $district, $category,
                $this->option('min-confidence') !== null ? (float) $this->option('min-confidence') : null,
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Satır', 'Aktarıldı (doğrulama bekliyor)', 'Elendi', 'Zaten kayıtlı', 'Düşük güven', 'İlçe eşleşmedi', 'Sektör eşleşmedi'], [[
            $stats['rows'], $stats['imported'], $stats['disqualified'], $stats['duplicate'], $stats['low_confidence'], $stats['no_district'], $stats['no_category'],
        ]]);

        return self::SUCCESS;
    }
}
