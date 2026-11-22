<?php

namespace Database\Seeders;

use App\Jobs\GenerateSiteJob;
use App\Models\Business;
use App\Models\Category;
use App\Models\District;
use App\Models\SearchJob;
use App\Services\Places\BusinessImporter;
use Illuminate\Database\Seeder;

/**
 * API anahtarı olmadan paneli denemek için örnek firmalar (Places yanıtı formatında).
 * Çalıştırma: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $district = District::whereHas('province', fn ($q) => $q->where('plate_code', 34))->where('name', 'Kadıköy')->firstOrFail();
        $category = Category::where('slug', 'pet-shop')->firstOrFail();

        $job = SearchJob::create([
            'province_id' => $district->province_id,
            'category_id' => $category->id,
            'district_ids' => [$district->id],
            'status' => 'completed',
            'finished_at' => now(),
        ]);

        $places = [
            ['id' => 'demo-1', 'displayName' => ['text' => 'Pati Dostu Pet Shop'], 'formattedAddress' => 'Caferağa, Moda Cd. No:10, 34710 Kadıköy/İstanbul', 'internationalPhoneNumber' => '+90 532 000 00 01', 'rating' => 4.8, 'userRatingCount' => 112, 'types' => ['pet_store'], 'location' => ['latitude' => 40.987, 'longitude' => 29.026], 'googleMapsUri' => 'https://maps.google.com/?cid=1', 'businessStatus' => 'OPERATIONAL'],
            ['id' => 'demo-2', 'displayName' => ['text' => 'Mırnav Evcil Hayvan Market'], 'formattedAddress' => 'Fenerbahçe, Bağdat Cd. No:5, 34726 Kadıköy/İstanbul', 'nationalPhoneNumber' => '0216 000 00 02', 'websiteUri' => 'https://instagram.com/mirnav', 'rating' => 4.5, 'userRatingCount' => 40, 'types' => ['pet_store'], 'businessStatus' => 'OPERATIONAL'],
            ['id' => 'demo-3', 'displayName' => ['text' => 'Akvaryum Dünyası'], 'formattedAddress' => 'Osmanağa, 34714 Kadıköy/İstanbul', 'nationalPhoneNumber' => '0216 000 00 03', 'websiteUri' => 'https://akvaryumdunyasi.example.com', 'types' => ['pet_store'], 'businessStatus' => 'OPERATIONAL'],
            ['id' => 'demo-4', 'displayName' => ['text' => 'Minik Patiler'], 'formattedAddress' => 'Göztepe, 34730 Kadıköy/İstanbul', 'internationalPhoneNumber' => '+90 555 000 00 04', 'rating' => 4.9, 'userRatingCount' => 23, 'types' => ['pet_store'], 'businessStatus' => 'OPERATIONAL'],
        ];

        $importer = app(BusinessImporter::class);

        foreach ($places as $place) {
            $business = $importer->import($place, $job, $district->id);

            if ($business) {
                $business->update(['reviews' => [
                    ['author' => 'Ayşe K.', 'rating' => 5, 'text' => 'Çalışanlar çok ilgili, kedim için doğru mamayı önerdiler. Fiyatlar da makul.', 'time' => '2 ay önce'],
                    ['author' => 'Mert D.', 'rating' => 5, 'text' => 'Mahallenin en güler yüzlü pet shopu, ürün çeşitliliği gayet iyi.', 'time' => '5 ay önce'],
                ], 'opening_hours' => ['Pazartesi: 09:00–21:00', 'Salı: 09:00–21:00', 'Çarşamba: 09:00–21:00', 'Perşembe: 09:00–21:00', 'Cuma: 09:00–21:00', 'Cumartesi: 10:00–21:00', 'Pazar: Kapalı']]);

                if ($business->contact_channel !== Business::CHANNEL_MANUAL) {
                    GenerateSiteJob::dispatchSync($business->id);
                }
            }
        }

        $job->update(['found_count' => count($places), 'qualified_count' => Business::where('search_job_id', $job->id)->where('status', '!=', 'disqualified')->count()]);
    }
}
