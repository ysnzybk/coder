<?php

namespace App\Services\Places;

use App\Models\Business;
use App\Models\Category;
use App\Models\District;
use App\Models\Province;
use App\Models\SearchJob;
use App\Support\TurkishText;
use RuntimeException;

/**
 * CSV dosyasından (ör. Overture Maps çıktısı) firma içe aktarır. Dosya kaynaklı kayıtlar
 * "Doğrulama bekliyor" durumuna düşer (web sitesi yok bilgisi elle teyit edilir).
 *
 * Beklenen sütunlar (başlık satırı zorunlu, sıra serbest):
 *   id, name, address, phone, website, email, social, lat, lng, category, confidence, status
 * Çok değerli alanlar "|" veya ";" ile ayrılabilir (ilki kullanılır).
 */
class FilePlacesImporter
{
    public function __construct(private readonly BusinessImporter $importer) {}

    /**
     * @return array<string, int> sayaçlar
     */
    public function import(string $path, Province $province, ?District $onlyDistrict = null, ?Category $forcedCategory = null, ?float $minConfidence = null): array
    {
        $minConfidence ??= (float) config('leadgen.file_import.min_confidence');
        $districts = $province->districts()->get();
        $otherProvinces = Province::where('id', '!=', $province->id)->pluck('name')->all();
        $categoryMap = config('leadgen.file_import.category_map');

        $stats = ['rows' => 0, 'imported' => 0, 'duplicate' => 0, 'low_confidence' => 0, 'no_district' => 0, 'no_category' => 0, 'disqualified' => 0];
        $jobs = [];   // category id => SearchJob
        $matchedDistricts = [];

        foreach ($this->rows($path) as $raw) {
            $stats['rows']++;
            $row = PlacesNormalizer::fromFileRow($raw);

            if ($row['confidence'] !== null && $row['confidence'] < $minConfidence) {
                $stats['low_confidence']++;

                continue;
            }

            $district = $this->matchDistrict($row['address'] ?? '', $districts, $otherProvinces, $province->name);
            if (! $district || ($onlyDistrict && $district->id !== $onlyDistrict->id)) {
                $stats['no_district']++;

                continue;
            }

            $category = $forcedCategory ?? $this->mapCategory($raw['category'] ?? '', $categoryMap);
            if (! $category) {
                $stats['no_category']++;

                continue;
            }

            $job = $jobs[$category->id] ??= SearchJob::create([
                'source' => 'file',
                'province_id' => $province->id,
                'category_id' => $category->id,
                'district_ids' => [],
                'status' => 'running',
            ]);

            if (Business::where('place_id', $row['external_id'])->exists()) {
                $stats['duplicate']++;

                continue;
            }

            $business = $this->importer->importRow($row, $job, $district->id);
            $matchedDistricts[$job->id][$district->id] = true;

            $business ? $stats['imported']++ : $stats['disqualified']++;
        }

        foreach ($jobs as $job) {
            $job->update([
                'status' => 'completed',
                'district_ids' => array_keys($matchedDistricts[$job->id] ?? []),
                'found_count' => $job->businesses()->count(),
                'qualified_count' => $job->businesses()->where('status', 'needs_verification')->count(),
                'finished_at' => now(),
            ]);
        }

        return $stats;
    }

    /** @return \Generator<array<string,string>> */
    private function rows(string $path): \Generator
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Dosya okunamadı: {$path}");
        }

        $handle = fopen($path, 'r');
        $first = fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first); // UTF-8 BOM
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = array_map(fn ($h) => mb_strtolower(trim($h)), str_getcsv($first, $delimiter));

        if (! in_array('name', $header, true) || ! in_array('id', $header, true)) {
            fclose($handle);

            throw new RuntimeException('CSV başlığında en az "id" ve "name" sütunları olmalı.');
        }

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($line === [null] || count($line) < 2) {
                continue;
            }
            yield array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), ''));
        }

        fclose($handle);
    }

    /** Adreste geçen en uzun ilçe adını bulur; başka ilin adı geçiyorsa ve seçilen il geçmiyorsa eşleşmez. */
    private function matchDistrict(string $address, $districts, array $otherProvinces, string $provinceName): ?District
    {
        $haystack = TurkishText::fold($address);

        if ($haystack === '') {
            return null;
        }

        $mentionsOther = collect($otherProvinces)->contains(fn ($p) => str_contains($haystack, TurkishText::fold($p)));
        if ($mentionsOther && ! str_contains($haystack, TurkishText::fold($provinceName))) {
            return null;
        }

        return $districts
            ->filter(fn (District $d) => str_contains($haystack, TurkishText::fold($d->name)))
            ->sortByDesc(fn (District $d) => mb_strlen($d->name))
            ->first();
    }

    private function mapCategory(string $sourceCategory, array $map): ?Category
    {
        $slug = $map[mb_strtolower(trim($sourceCategory))] ?? null;

        return $slug ? Category::where('slug', $slug)->first() : null;
    }
}
