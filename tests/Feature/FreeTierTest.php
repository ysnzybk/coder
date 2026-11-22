<?php

namespace Tests\Feature;

use App\Jobs\GenerateSiteJob;
use App\Jobs\RunSearchJob;
use App\Models\ApiUsage;
use App\Models\Business;
use App\Models\Category;
use App\Models\District;
use App\Models\Province;
use App\Models\SearchJob;
use App\Services\Ai\ClaudeContentGenerator;
use App\Services\Ai\CloudflareContentGenerator;
use App\Services\Ai\SiteContentGenerator;
use App\Services\Ai\TemplateContentGenerator;
use App\Services\Images\CloudflareBudget;
use App\Services\Images\CloudflareBudgetExceeded;
use App\Services\Images\CloudflareImageGenerator;
use App\Services\Places\BusinessImporter;
use App\Services\Places\PlacesBudget;
use App\Services\Places\PlacesBudgetExceeded;
use App\Services\Places\PlacesClient;
use Database\Seeders\CategorySeeder;
use Database\Seeders\MessageTemplateSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FreeTierTest extends TestCase
{
    use RefreshDatabase;

    private Province $province;

    private District $district;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed([CategorySeeder::class, MessageTemplateSeeder::class]);

        $this->province = Province::create(['plate_code' => 34, 'name' => 'İstanbul', 'slug' => 'istanbul']);
        Province::create(['plate_code' => 6, 'name' => 'Ankara', 'slug' => 'ankara']);
        $this->district = District::create(['province_id' => $this->province->id, 'name' => 'Kadıköy', 'slug' => 'kadikoy']);
        District::create(['province_id' => $this->province->id, 'name' => 'Üsküdar', 'slug' => 'uskudar']);
        $this->category = Category::where('slug', 'pet-shop')->firstOrFail();
    }

    private function cloudflareOn(): void
    {
        config(['leadgen.cloudflare.account_id' => 'acc', 'leadgen.cloudflare.token' => 'tok', 'leadgen.ai_provider' => 'cloudflare']);
    }

    private function business(array $extra = []): Business
    {
        return Business::create($extra + [
            'place_id' => uniqid('p'), 'name' => 'Pati Dostu', 'phone' => '+905321112233', 'phone_type' => 'mobile',
            'contact_channel' => 'whatsapp', 'status' => 'discovered', 'category_id' => $this->category->id,
            'province_id' => $this->province->id, 'district_id' => $this->district->id, 'reviews' => [],
        ]);
    }

    private function llmContent(array $override = []): array
    {
        return $override + [
            'hero_title' => 'Pati Dostu Pet Shop', 'hero_subtitle' => 'Kadıköy’de dostlarınız için her şey', 'tagline' => 'Pet shop · Kadıköy',
            'about' => 'Bizi 0532 111 22 33 numarasından arayın. Mahallenin pet shopu.',
            'highlights' => [['icon' => '🐾', 'title' => 'Geniş seçenek', 'text' => 'Mama ve aksesuar.']],
            'services' => [
                ['name' => 'Mama', 'description' => 'Kedi ve köpek maması.'],
                ['name' => 'Aksesuar', 'description' => 'Tasma ve oyuncak.'],
                ['name' => 'Bakım', 'description' => 'Şampuan ve tarak.'],
            ],
            'review_summary' => '', 'faq' => [['q' => 'Saatler?', 'a' => 'Bizi arayın.']], 'cta_text' => 'Hemen arayın',
            'palette' => 'orman', 'font_style' => 'samimi', 'seo_title' => 'Pati Dostu', 'seo_description' => 'Kadıköy pet shop',
            'image_prompts' => ['hero' => 'pet shop interior', 'gallery_1' => 'happy dog', 'gallery_2' => 'cat toys'],
        ];
    }

    private function cfResponse(mixed $response, int $in = 900, int $out = 900): Response|PromiseInterface
    {
        return Http::response(['success' => true, 'result' => ['response' => $response, 'usage' => ['prompt_tokens' => $in, 'completion_tokens' => $out]]]);
    }

    // ---------- Cloudflare metin üretimi ----------

    public function test_cloudflare_generator_returns_sanitized_content_and_spends_budget(): void
    {
        $this->cloudflareOn();
        Http::fake(['api.cloudflare.com/*' => $this->cfResponse($this->llmContent())]);

        $content = app(CloudflareContentGenerator::class)->generate($this->business());

        $this->assertSame('cloudflare', $content['_source']);
        $this->assertCount(3, $content['services']);
        $this->assertStringNotContainsString('0532', $content['about'], 'Telefon numarası ayıklanmalı');
        $this->assertSame('orman', $content['palette']);
        $this->assertGreaterThan(0, app(CloudflareBudget::class)->used());

        Http::assertSent(fn ($r) => str_contains($r->url(), 'llama-3.3-70b')
            && $r['response_format']['type'] === 'json_schema'
            && $r['messages'][1]['role'] === 'user');
    }

    public function test_cloudflare_generator_extracts_json_from_code_fence_string(): void
    {
        $this->cloudflareOn();
        Http::fake(['api.cloudflare.com/*' => $this->cfResponse("```json\n".json_encode($this->llmContent())."\n```")]);

        $this->assertSame('cloudflare', app(CloudflareContentGenerator::class)->generate($this->business())['_source']);
    }

    public function test_invalid_output_twice_falls_back_to_template(): void
    {
        $this->cloudflareOn();
        Http::fake(['api.cloudflare.com/*' => $this->cfResponse('üzgünüm, JSON veremedim')]);

        $content = app(CloudflareContentGenerator::class)->generate($this->business());

        $this->assertSame('template', $content['_source']);
        Http::assertSentCount(2);
    }

    public function test_exhausted_budget_throws_without_calling_api_and_429_exhausts_it(): void
    {
        $this->cloudflareOn();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['message' => 'daily free allocation of 10,000 neurons exceeded']]], 429)]);

        try {
            app(CloudflareContentGenerator::class)->generate($this->business());
            $this->fail('Bütçe aşımı bekleniyordu');
        } catch (CloudflareBudgetExceeded $e) {
            $this->assertGreaterThanOrEqual(60, $e->secondsUntilReset);
        }

        $this->assertSame(0, app(CloudflareBudget::class)->remaining());

        Http::fake(); // artık hiçbir istek atılmamalı
        $this->expectException(CloudflareBudgetExceeded::class);
        app(CloudflareContentGenerator::class)->generate($this->business());
    }

    public function test_site_job_is_released_not_failed_when_neuron_budget_is_exhausted(): void
    {
        $this->mock(SiteContentGenerator::class, fn ($m) => $m->shouldReceive('generate')->andThrow(new CloudflareBudgetExceeded(3600)));
        $business = $this->business();

        $job = (new GenerateSiteJob($business->id))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);

        $job->assertReleased(3600);
        $this->assertSame(Business::STATUS_GENERATING, $business->fresh()->status);
        $this->assertNull($business->fresh()->site);
    }

    public function test_provider_selection_and_safe_fallback(): void
    {
        config(['leadgen.ai_provider' => 'cloudflare', 'leadgen.cloudflare.account_id' => '', 'leadgen.cloudflare.token' => '']);
        $this->assertInstanceOf(TemplateContentGenerator::class, app(SiteContentGenerator::class));

        $this->cloudflareOn();
        $this->assertInstanceOf(CloudflareContentGenerator::class, app(SiteContentGenerator::class));

        config(['leadgen.ai_provider' => 'anthropic', 'leadgen.anthropic.key' => '']);
        $this->assertInstanceOf(TemplateContentGenerator::class, app(SiteContentGenerator::class));

        config(['leadgen.anthropic.key' => 'sk-test']);
        $this->assertInstanceOf(ClaudeContentGenerator::class, app(SiteContentGenerator::class));

        config(['leadgen.ai_provider' => 'template']);
        $this->assertInstanceOf(TemplateContentGenerator::class, app(SiteContentGenerator::class));
    }

    public function test_image_is_resized_compressed_and_real_neurons_are_counted(): void
    {
        $this->cloudflareOn();
        Storage::fake('public');

        $gd = imagecreatetruecolor(1024, 1024);
        imagefilledrectangle($gd, 0, 0, 1024, 1024, imagecolorallocate($gd, 200, 90, 40));
        ob_start();
        imagejpeg($gd, null, 95);
        $raw = ob_get_clean();

        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'result' => ['image' => base64_encode($raw), 'usage' => ['neurons' => 134.4]]])]);

        $path = app(CloudflareImageGenerator::class)->generate('a shop', 'sites/x/hero.jpg', 3);

        Storage::disk('public')->assertExists($path);
        [$w] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(960, $w);
        $this->assertSame(135, app(CloudflareBudget::class)->used(), 'Gerçek usage.neurons (134,4 → 135) sayılmalı');
        Http::assertSent(fn ($r) => $r['steps'] === 3);

        $this->assertEqualsWithDelta(134.4, app(CloudflareBudget::class)->imageCost(3), 0.01);
        $this->assertEqualsWithDelta(96.0, app(CloudflareBudget::class)->imageCost(2), 0.01);
    }

    // ---------- Google Places ücretsiz kota ----------

    private function placesPage(string $id, ?string $next = null): array
    {
        return ['places' => [[
            'id' => $id, 'displayName' => ['text' => "Firma {$id}"], 'formattedAddress' => 'Moda, Kadıköy/İstanbul',
            'internationalPhoneNumber' => '+90 532 111 22 33', 'businessStatus' => 'OPERATIONAL',
        ]]] + ($next ? ['nextPageToken' => $next] : []);
    }

    public function test_places_monthly_limit_truncates_pages_and_marks_search_partial(): void
    {
        Bus::fake([GenerateSiteJob::class]);
        config(['leadgen.places.key' => 'k', 'leadgen.places.monthly_text_limit' => 2, 'leadgen.places.max_pages' => 3]);

        Http::fake(['places.googleapis.com/*' => Http::sequence()
            ->push($this->placesPage('a', 't1'))->push($this->placesPage('b', 't2'))->push($this->placesPage('c'))]);

        $job = SearchJob::create(['province_id' => $this->province->id, 'category_id' => $this->category->id, 'district_ids' => [$this->district->id]]);
        RunSearchJob::dispatchSync($job->id);

        Http::assertSentCount(2);                                   // 3. sayfa kota nedeniyle istenmedi
        $this->assertSame(2, Business::count());
        $this->assertSame('partial', $job->fresh()->status);
        $this->assertStringContainsString('ücretsiz kota', $job->fresh()->notice);
        $this->assertSame(2, app(PlacesBudget::class)->used(PlacesBudget::TEXT));

        // Kota doluyken yeni arama hiç istek atmaz
        Http::fake();
        $job2 = SearchJob::create(['province_id' => $this->province->id, 'category_id' => $this->category->id, 'district_ids' => [$this->district->id]]);
        RunSearchJob::dispatchSync($job2->id);

        Http::assertNothingSent();
        $this->assertSame('partial', $job2->fresh()->status);
    }

    public function test_places_details_limit_throws_and_site_is_generated_without_reviews(): void
    {
        config(['leadgen.places.key' => 'k', 'leadgen.places.monthly_details_limit' => 1]);
        Http::fake(['places.googleapis.com/*' => Http::response(['reviews' => [['rating' => 5, 'text' => ['text' => 'Harika']]]])]);

        $client = app(PlacesClient::class);
        $client->details('x1');                                     // 1/1
        $this->assertSame(1, app(PlacesBudget::class)->used(PlacesBudget::DETAILS));

        $this->expectException(PlacesBudgetExceeded::class);
        try {
            $client->details('x2');
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_site_generation_survives_exhausted_details_budget(): void
    {
        config(['leadgen.places.key' => 'k', 'leadgen.places.monthly_details_limit' => 1, 'leadgen.ai_provider' => 'template']);
        ApiUsage::hit(app(PlacesBudget::class)->period(), PlacesBudget::DETAILS);
        Http::fake();

        $business = $this->business(['source' => 'google']);
        GenerateSiteJob::dispatchSync($business->id);

        Http::assertNothingSent();
        $this->assertSame(Business::STATUS_SITE_READY, $business->fresh()->status);
        $this->assertNull($business->fresh()->reviews === [] ? null : $business->fresh()->reviews);
    }

    // ---------- Dosyadan içe aktarma + doğrulama kapısı ----------

    private function csv(): string
    {
        $rows = [
            'id,name,address,phone,website,email,social,lat,lng,category,confidence,status',
            'o1,Mavi Pet,"Moda Cd. 5, Kadıköy, İstanbul",+90 532 111 00 01,,,,40.98,29.02,pet_store,0.9,open',
            'o2,Sabit Hatlı Pet,"Bağdat Cd. 9, Kadıköy, İstanbul",+90 216 111 00 02,,,https://instagram.com/sabit,40.97,29.05,pet_store,0.8,open',
            'o3,Sitesi Olan Pet,"Moda, Kadıköy, İstanbul",+90 532 111 00 03,https://sitesiolan.com,,,40.9,29.0,pet_store,0.95,open',
            'o4,Düşük Güven Pet,"Moda, Kadıköy, İstanbul",+90 532 111 00 04,,,,40.9,29.0,pet_store,0.2,open',
            'o5,Ankara Pet,"Çankaya, Ankara",+90 532 111 00 05,,,,39.9,32.8,pet_store,0.9,open',
            'o6,Kapalı Pet,"Moda, Kadıköy, İstanbul",+90 532 111 00 06,,,,40.9,29.0,pet_store,0.9,permanently_closed',
            'o7,Üsküdarlı Pet,"Üsküdar, İstanbul",+90 532 111 00 07,,,,41.0,29.0,pet_store,0.9,open',
            'o8,Bilinmeyen Kategori,"Moda, Kadıköy, İstanbul",+90 532 111 00 08,,,,40.9,29.0,casino,0.9,open',
            'o1,Mavi Pet Tekrar,"Moda Cd. 5, Kadıköy, İstanbul",+90 532 111 00 01,,,,40.98,29.02,pet_store,0.9,open',
        ];
        $path = sys_get_temp_dir().'/import-'.uniqid().'.csv';
        file_put_contents($path, "\xEF\xBB\xBF".implode("\n", $rows));

        return $path;
    }

    public function test_file_import_routes_rows_and_gates_everything_behind_verification(): void
    {
        Bus::fake([GenerateSiteJob::class]);

        $this->artisan('places:import-file', ['dosya' => $this->csv(), '--il' => 'İstanbul', '--ilce' => 'Kadıköy'])->assertSuccessful();

        $by = fn (string $id) => Business::where('place_id', "file:{$id}")->first();

        $this->assertSame(Business::STATUS_NEEDS_VERIFICATION, $by('o1')->status);
        $this->assertSame('file', $by('o1')->source);
        $this->assertSame('whatsapp', $by('o1')->contact_channel);
        $this->assertSame($this->district->id, $by('o1')->district_id);

        $this->assertSame(Business::STATUS_NEEDS_VERIFICATION, $by('o2')->status);
        $this->assertSame('manual', $by('o2')->contact_channel);
        $this->assertSame('social_only', $by('o2')->website_status);

        $this->assertSame(Business::STATUS_DISQUALIFIED, $by('o3')->status);   // web sitesi var
        $this->assertNull($by('o4'));                                          // düşük güven
        $this->assertNull($by('o5'));                                          // başka il
        $this->assertSame(Business::STATUS_DISQUALIFIED, $by('o6')->status);   // kapalı
        $this->assertNull($by('o7'));                                          // --ilce Kadıköy dışında
        $this->assertNull($by('o8'));                                          // sektör eşleşmedi
        $this->assertSame(1, Business::where('name', 'like', 'Mavi Pet%')->count(), 'Tekrar çalıştırmada çift kayıt olmaz');

        // Doğrulanana kadar hiçbir site üretimi başlamaz
        Bus::assertNotDispatched(GenerateSiteJob::class);
        $this->assertSame('file', SearchJob::latest('id')->first()->source);
    }

    public function test_verification_actions_start_generation_or_reject(): void
    {
        Bus::fake([GenerateSiteJob::class]);
        $this->artisan('places:import-file', ['dosya' => $this->csv(), '--il' => '34', '--kategori' => 'pet-shop'])->assertSuccessful();

        $importer = app(BusinessImporter::class);
        $o1 = Business::where('place_id', 'file:o1')->first();
        $o2 = Business::where('place_id', 'file:o2')->first();
        $o7 = Business::where('place_id', 'file:o7')->first();

        $importer->confirmNoWebsite($o1);
        $this->assertSame(Business::STATUS_DISCOVERED, $o1->fresh()->status);
        Bus::assertDispatched(GenerateSiteJob::class, fn ($j) => $j->businessId === $o1->id);

        $importer->confirmNoWebsite($o2);   // manuel kanal: site üretimi başlamaz
        $this->assertSame(Business::STATUS_MANUAL_CONTACT, $o2->fresh()->status);
        Bus::assertNotDispatched(GenerateSiteJob::class, fn ($j) => $j->businessId === $o2->id);

        $importer->rejectHasWebsite($o7);
        $this->assertSame(Business::STATUS_DISQUALIFIED, $o7->fresh()->status);
        $this->assertSame('real', $o7->fresh()->website_status);
    }

    public function test_file_import_rejects_csv_without_required_columns(): void
    {
        $path = sys_get_temp_dir().'/bad-'.uniqid().'.csv';
        file_put_contents($path, "foo,bar\n1,2\n");

        $this->artisan('places:import-file', ['dosya' => $path, '--il' => 'İstanbul'])->assertFailed();
    }
}
