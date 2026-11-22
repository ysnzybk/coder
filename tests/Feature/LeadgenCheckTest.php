<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadgenCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_check_fails_on_an_unconfigured_install_and_reports_why(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://localhost']);

        $this->artisan('leadgen:check', ['--production' => true])
            ->expectsOutputToContain('APP_DEBUG')
            ->expectsOutputToContain('Admin kullanıcı')
            ->assertFailed();
    }

    public function test_check_passes_when_critical_requirements_are_met(): void
    {
        $this->seed(LocationSeeder::class);
        User::factory()->create();
        config(['cache.default' => 'database', 'queue.default' => 'database']);

        // storage bağlantısı / izinler ortama bağlı; kritik olmayanlar uyarı olarak kalır
        @symlink(storage_path('app/public'), public_path('storage'));

        $this->artisan('leadgen:check')->assertSuccessful();
    }
}
