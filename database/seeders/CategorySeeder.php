<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // [ad, arama terimi, Places includedType, şablon]
            ['Pet shop', 'pet shop', 'pet_store', 'petshop'],
            ['Veteriner', 'veteriner kliniği', 'veterinary_care', 'petshop'],
            ['Restoran', 'restoran', 'restaurant', 'restoran'],
            ['Kafe', 'kafe', 'cafe', 'restoran'],
            ['Pastane / Fırın', 'pastane', 'bakery', 'restoran'],
            ['Kuaför', 'kuaför', 'hair_care', 'guzellik'],
            ['Berber', 'berber', 'barber_shop', 'guzellik'],
            ['Güzellik salonu', 'güzellik salonu', 'beauty_salon', 'guzellik'],
            ['Oto servis', 'oto servis', 'car_repair', 'oto'],
            ['Oto yıkama', 'oto yıkama', 'car_wash', 'oto'],
            ['Lastikçi', 'lastikçi', null, 'oto'],
            ['Çiçekçi', 'çiçekçi', 'florist', 'genel'],
        ];

        foreach ($rows as [$name, $term, $type, $template]) {
            Category::updateOrCreate(
                ['slug' => Str::slug(strtr($name, ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'Ç' => 'c']))],
                ['name' => $name, 'search_term' => $term, 'included_type' => $type, 'template' => $template],
            );
        }
    }
}
