<?php

namespace Database\Seeders;

use App\Jobs\GenerateSiteJob;
use App\Models\Business;
use App\Models\Category;
use App\Models\District;
use App\Models\SearchJob;
use App\Models\Site;
use App\Services\Places\BusinessImporter;
use App\Services\Sites\Themes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * API anahtarı olmadan paneli ve tüm sektör şablonlarını denemek için örnek firmalar
 * (Places yanıtı formatında). Çalıştırma: php artisan db:seed --class=DemoSeeder
 * Görseller GD ile üretilen yer tutuculardır (gerçek sistemde Cloudflare üretir).
 */
class DemoSeeder extends Seeder
{
    private const SECTORS = [
        'pet-shop' => [
            ['demo-1', 'Pati Dostu Pet Shop', '+90 532 000 00 01', 'pet_store', 4.8, 112, ['Çalışanlar çok ilgili, kedim için doğru mamayı önerdiler. Fiyatlar da makul.', 'Mahallenin en güler yüzlü pet shopu, ürün çeşitliliği gayet iyi.']],
            ['demo-4', 'Minik Patiler', '+90 555 000 00 04', 'pet_store', 4.9, 23, ['Köpeğim için tasma ve oyuncak aldık, çok memnun kaldık.']],
        ],
        'restoran' => [
            ['demo-10', 'Lezzet Durağı Restoran', '+90 533 000 00 10', 'restaurant', 4.6, 340, ['Yemekler taze ve porsiyonlar doyurucu. Servis hızlıydı.', 'Ailece gittik, ortam sakin ve temizdi.', 'Kahvaltısı çok güzel, hafta sonu rezervasyon yapmanızı öneririm.']],
        ],
        'kuafor' => [
            ['demo-20', 'Mavi Makas Kuaför', '+90 534 000 00 20', 'hair_care', 4.9, 87, ['Saçımı tam istediğim gibi kestiler, çok titizler.', 'Randevu saatine sadık kalıyorlar, ortam çok şık.']],
        ],
        'oto-servis' => [
            ['demo-30', 'Güven Oto Servis', '+90 535 000 00 30', 'car_repair', 4.7, 156, ['Arızayı hızlıca buldular, açıklamalarında net oldular.', 'Periyodik bakımı zamanında teslim ettiler, tavsiye ederim.']],
        ],
        'cicekci' => [
            ['demo-40', 'Papatya Çiçekçilik', '+90 536 000 00 40', 'florist', 4.8, 64, ['Siparişim söz verilen saatte ulaştı, çiçekler çok tazeydi.']],
        ],
    ];

    private const HOURS = ['Pazartesi: 09:00–21:00', 'Salı: 09:00–21:00', 'Çarşamba: 09:00–21:00', 'Perşembe: 09:00–21:00', 'Cuma: 09:00–21:00', 'Cumartesi: 10:00–21:00', 'Pazar: Kapalı'];

    public function run(): void
    {
        $district = District::whereHas('province', fn ($q) => $q->where('plate_code', 34))->where('name', 'Kadıköy')->firstOrFail();
        $importer = app(BusinessImporter::class);

        foreach (self::SECTORS as $slug => $rows) {
            $category = Category::where('slug', $slug)->firstOrFail();

            $job = SearchJob::create([
                'province_id' => $district->province_id, 'category_id' => $category->id,
                'district_ids' => [$district->id], 'status' => 'completed', 'finished_at' => now(),
            ]);

            foreach ($rows as [$id, $name, $phone, $type, $rating, $count, $reviews]) {
                $business = $importer->import([
                    'id' => $id, 'displayName' => ['text' => $name],
                    'formattedAddress' => 'Caferağa Mah. Moda Cd. No:10, 34710 Kadıköy/İstanbul',
                    'internationalPhoneNumber' => $phone, 'rating' => $rating, 'userRatingCount' => $count,
                    'types' => [$type], 'location' => ['latitude' => 40.987, 'longitude' => 29.026],
                    'googleMapsUri' => 'https://maps.google.com/?cid='.crc32($id), 'businessStatus' => 'OPERATIONAL',
                ], $job, $district->id);

                if (! $business) {
                    continue;
                }

                $business->update([
                    'reviews' => array_map(fn ($t) => ['author' => 'Google kullanıcısı', 'rating' => 5, 'text' => $t, 'time' => '2 ay önce'], $reviews),
                    'opening_hours' => self::HOURS,
                ]);

                GenerateSiteJob::dispatchSync($business->id);
                $this->placeholderImages($business->fresh('site')->site);
            }

            $job->update([
                'found_count' => count($rows),
                'qualified_count' => Business::where('search_job_id', $job->id)->where('status', '!=', 'disqualified')->count(),
            ]);
        }

        $this->socialAndLandline($district);
    }

    /** Manuel iletişim ve elenen örnekleri (panelde sekmeleri görmek için). */
    private function socialAndLandline(District $district): void
    {
        $category = Category::where('slug', 'pet-shop')->firstOrFail();
        $job = SearchJob::where('category_id', $category->id)->first();
        $importer = app(BusinessImporter::class);

        $importer->import(['id' => 'demo-2', 'displayName' => ['text' => 'Mırnav Evcil Hayvan Market'], 'formattedAddress' => 'Fenerbahçe, Kadıköy/İstanbul', 'nationalPhoneNumber' => '0216 000 00 02', 'websiteUri' => 'https://instagram.com/mirnav', 'rating' => 4.5, 'userRatingCount' => 40, 'businessStatus' => 'OPERATIONAL'], $job, $district->id);
        $importer->import(['id' => 'demo-3', 'displayName' => ['text' => 'Akvaryum Dünyası'], 'formattedAddress' => 'Osmanağa, Kadıköy/İstanbul', 'nationalPhoneNumber' => '0216 000 00 03', 'websiteUri' => 'https://akvaryumdunyasi.example.com', 'businessStatus' => 'OPERATIONAL'], $job, $district->id);
    }

    /** Cloudflare yokken yerleşimi görmek için GD ile renkli yer tutucu görseller. */
    private function placeholderImages(?Site $site): void
    {
        if (! $site || ! function_exists('imagecreatetruecolor')) {
            return;
        }

        $palette = Themes::palette($site->palette);
        $paths = [];

        foreach (['hero' => 0, 'gallery_1' => 1, 'gallery_2' => 2] as $key => $i) {
            $img = imagecreatetruecolor(1400, 1000);
            [$r1, $g1, $b1] = sscanf($i % 2 ? $palette['primary'] : $palette['primary_dark'], '#%02x%02x%02x');
            [$r2, $g2, $b2] = sscanf($palette['accent'], '#%02x%02x%02x');

            for ($y = 0; $y < 1000; $y++) {
                $t = $y / 1000;
                imageline($img, 0, $y, 1400, $y, imagecolorallocate($img, (int) ($r1 + ($r2 - $r1) * $t * .6), (int) ($g1 + ($g2 - $g1) * $t * .6), (int) ($b1 + ($b2 - $b1) * $t * .6)));
            }
            for ($c = 0; $c < 7; $c++) {
                $col = imagecolorallocatealpha($img, 255, 255, 255, 105 + $c * 2);
                imagefilledellipse($img, (($c * 337 + $i * 191) % 1400), (($c * 211 + $i * 97) % 1000), 260 + $c * 60, 260 + $c * 60, $col);
            }

            $path = "sites/{$site->slug}/{$key}.jpg";
            ob_start();
            imagejpeg($img, null, 82);
            Storage::disk('public')->put($path, ob_get_clean());
            $paths[$key] = $path;
        }

        $site->update(['images' => $paths]);
    }
}
