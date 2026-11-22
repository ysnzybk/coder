<?php

namespace App\Support;

use App\Models\Business;
use App\Services\Phone\TurkishPhone;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    public static function businesses(Collection $records): StreamedResponse
    {
        $records->loadMissing(['province', 'district', 'category', 'site', 'deal']);

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel için UTF-8 BOM
            fputcsv($out, ['Firma', 'İl', 'İlçe', 'Sektör', 'Telefon', 'E-posta', 'Kanal', 'Durum', 'Arama sonucu', 'Puan', 'Yorum', 'Site', 'Kurulum', 'Bakım', 'Adres'], ';');

            foreach ($records as $b) {
                fputcsv($out, array_map([self::class, 'safe'], [
                    $b->name, $b->province?->name, $b->district?->name, $b->category?->name,
                    TurkishPhone::display($b->phone), $b->email,
                    Business::CHANNELS[$b->contact_channel] ?? '', Business::STATUSES[$b->status] ?? $b->status,
                    Business::CALL_OUTCOMES[$b->call_outcome] ?? '', $b->rating, $b->review_count,
                    $b->site?->url(), $b->deal?->setup_price, $b->deal?->maintenance_price, $b->address,
                ]), ';');
            }

            fclose($out);
        }, 'firmalar-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * CSV/Excel formül enjeksiyonunu önler: "=", "+", "-", "@", sekme ve satır başı ile başlayan metinleri
     * (ör. Google'dan gelen firma adı "=HYPERLINK(...)") başına tek tırnak koyarak düz metne çevirir.
     */
    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
