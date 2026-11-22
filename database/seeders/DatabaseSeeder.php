<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            CategorySeeder::class,
            MessageTemplateSeeder::class,
        ]);

        // Tek admin: .env'deki ADMIN_EMAIL / ADMIN_PASSWORD ile
        if (($email = env('ADMIN_EMAIL')) && ($password = env('ADMIN_PASSWORD'))) {
            User::updateOrCreate(['email' => $email], ['name' => 'Admin', 'password' => $password]);
        }
    }
}
