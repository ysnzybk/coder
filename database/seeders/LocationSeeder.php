<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use App\Services\Sites\SlugGenerator;
use Illuminate\Database\Seeder;

/** 81 il / 973 ilçe (database/data/tr_provinces_districts.json) */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $slugger = new SlugGenerator;
        $data = json_decode(file_get_contents(database_path('data/tr_provinces_districts.json')), true);

        foreach ($data as $row) {
            $province = Province::updateOrCreate(
                ['plate_code' => $row['plate_code']],
                ['name' => $row['name'], 'slug' => $slugger->slugify($row['name'])],
            );

            foreach ($row['districts'] as $name) {
                District::updateOrCreate(
                    ['province_id' => $province->id, 'slug' => $slugger->slugify($name)],
                    ['name' => $name],
                );
            }
        }
    }
}
