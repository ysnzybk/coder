<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Site;
use App\Support\CsvExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function siteFor(string $name, array $content = []): Site
    {
        $b = Business::create(['place_id' => uniqid(), 'name' => $name, 'address' => $name, 'phone' => '+905321112233', 'phone_type' => 'mobile', 'status' => 'site_ready', 'contact_channel' => 'whatsapp', 'reviews' => [['author' => $name, 'rating' => 5, 'text' => $name]]]);

        return Site::create(['business_id' => $b->id, 'slug' => 'test-site', 'template' => 'genel', 'content' => $content + ['hero_title' => $name, 'about' => $name, 'services' => [['name' => $name, 'description' => $name]]], 'palette' => 'okyanus']);
    }

    public function test_hostile_business_data_cannot_break_out_of_html_or_json_ld(): void
    {
        $evil = '</script><script>window.pwned=1</script><img src=x onerror=alert(1)>';
        $this->siteFor($evil);

        $html = $this->get('/test-site')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>window.pwned', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringContainsString('\\u003C/script\\u003E', $html, 'JSON-LD içinde < ve > kaçışlanmalı');
    }

    public function test_security_headers_are_present_on_public_and_admin_pages(): void
    {
        $this->siteFor('Güvenli Firma');

        foreach (['/test-site', '/admin/login'] as $url) {
            $this->get($url)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_csv_export_neutralizes_spreadsheet_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\",\"a\")", CsvExport::safe('=HYPERLINK("http://x","a")'));
        $this->assertSame("'+90 532", CsvExport::safe('+90 532'));
        $this->assertSame("'-1", CsvExport::safe('-1'));
        $this->assertSame("'@SUM(A1)", CsvExport::safe('@SUM(A1)'));
        $this->assertSame('Normal Firma', CsvExport::safe('Normal Firma'));
        $this->assertSame(12, CsvExport::safe(12));
        $this->assertNull(CsvExport::safe(null));
    }

    public function test_tracking_and_optout_endpoints_do_not_leak_or_redirect_elsewhere(): void
    {
        $this->get('/t/c/yok-boyle-bir-token')->assertNotFound();
        $this->get('/u/yok-boyle-bir-token')->assertNotFound();
        $this->get('/t/o/yok-boyle-bir-token.gif')->assertOk()->assertHeader('Content-Type', 'image/gif'); // piksel her zaman döner
        $this->post('/u/yok-boyle-bir-token')->assertNotFound(); // (CSRF testlerde kapalıdır; canlıda 419 döner)
    }
}
