<?php

namespace Tests\Feature;

use App\Jobs\GenerateSiteJob;
use App\Jobs\RunSearchJob;
use App\Models\Business;
use App\Models\Category;
use App\Models\District;
use App\Models\OptOut;
use App\Models\OutreachMessage;
use App\Models\Province;
use App\Models\SearchJob;
use Database\Seeders\CategorySeeder;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadPipelineTest extends TestCase
{
    use RefreshDatabase;

    private SearchJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, MessageTemplateSeeder::class]);

        $province = Province::create(['plate_code' => 34, 'name' => 'İstanbul', 'slug' => 'istanbul']);
        $district = District::create(['province_id' => $province->id, 'name' => 'Kadıköy', 'slug' => 'kadikoy']);

        $this->job = SearchJob::create([
            'province_id' => $province->id,
            'category_id' => Category::where('slug', 'pet-shop')->value('id'),
            'district_ids' => [$district->id],
        ]);
    }

    private function place(string $id, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'displayName' => ['text' => "Firma {$id}"],
            'formattedAddress' => 'Moda, 34710 Kadıköy/İstanbul',
            'businessStatus' => 'OPERATIONAL',
            'types' => ['pet_store'],
        ], $extra);
    }

    public function test_search_qualifies_businesses_and_routes_channels(): void
    {
        Bus::fake([GenerateSiteJob::class]);
        config(['leadgen.places.key' => 'test-key']);
        OptOut::create(['value' => '+905559998877', 'type' => 'phone']);

        Http::fake(['places.googleapis.com/v1/places:searchText' => Http::response(['places' => [
            $this->place('mobile', ['internationalPhoneNumber' => '+90 532 111 22 33']),
            $this->place('landline', ['nationalPhoneNumber' => '0216 111 22 33', 'websiteUri' => 'https://instagram.com/x']),
            $this->place('website', ['internationalPhoneNumber' => '+90 532 111 22 44', 'websiteUri' => 'https://firma.com']),
            $this->place('closed', ['internationalPhoneNumber' => '+90 532 111 22 55', 'businessStatus' => 'CLOSED_PERMANENTLY']),
            $this->place('optout', ['internationalPhoneNumber' => '+90 555 999 88 77']),
            $this->place('otherdistrict', ['internationalPhoneNumber' => '+90 532 111 22 66', 'formattedAddress' => 'Üsküdar/İstanbul']),
        ]])]);

        RunSearchJob::dispatchSync($this->job->id);

        $b = fn (string $id) => Business::where('place_id', $id)->first();

        $this->assertSame(Business::CHANNEL_WHATSAPP, $b('mobile')->contact_channel);
        $this->assertSame(Business::STATUS_DISCOVERED, $b('mobile')->status);

        $this->assertSame(Business::CHANNEL_MANUAL, $b('landline')->contact_channel);
        $this->assertSame(Business::STATUS_MANUAL_CONTACT, $b('landline')->status);
        $this->assertSame('social_only', $b('landline')->website_status);

        $this->assertSame(Business::STATUS_DISQUALIFIED, $b('website')->status);
        $this->assertSame(Business::STATUS_DISQUALIFIED, $b('closed')->status);
        $this->assertSame(Business::STATUS_OPTED_OUT, $b('optout')->status);
        $this->assertNull($b('otherdistrict'));

        // Yalnızca otomatik kanaldaki (cep) firma için site üretimi başlar; manuel için başlamaz.
        Bus::assertDispatchedTimes(GenerateSiteJob::class, 1);
        Bus::assertDispatched(GenerateSiteJob::class, fn ($j) => $j->businessId === $b('mobile')->id);

        $this->assertSame('completed', $this->job->fresh()->status);
        $this->assertSame(2, $this->job->fresh()->qualified_count);

        // Tekrar aramada aynı firma tekrar işlenmez
        RunSearchJob::dispatchSync($this->job->id);
        $this->assertSame(6 - 1, Business::count());
    }

    public function test_site_generation_creates_site_and_pending_draft_without_sending(): void
    {
        $business = Business::create([
            'place_id' => 'p1', 'name' => 'Pati Dostu', 'phone' => '+905321112233', 'phone_type' => 'mobile',
            'contact_channel' => 'whatsapp', 'status' => 'discovered',
            'category_id' => $this->job->category_id, 'province_id' => $this->job->province_id,
            'district_id' => $this->job->district_ids[0], 'reviews' => [],
        ]);

        GenerateSiteJob::dispatchSync($business->id);

        $business->refresh();
        $this->assertSame(Business::STATUS_SITE_READY, $business->status);
        $this->assertSame('pati-dostu', $business->site->slug);
        $this->assertSame('template', $business->site->content['_source']);

        $message = $business->outreachMessages()->sole();
        $this->assertSame(OutreachMessage::STATUS_DRAFT, $message->status);
        $this->assertStringContainsString('https://test.local/pati-dostu?r='.$message->token, $message->body);
        $this->assertStringContainsString('15.000 TL', $message->body);
        $this->assertStringContainsString('https://test.local/u/'.$message->token, $message->body);

        $this->get('/pati-dostu')->assertOk()->assertSee('Pati Dostu')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        // Yeniden üretim yeni taslak oluşturmaz
        GenerateSiteJob::dispatchSync($business->id);
        $this->assertSame(1, $business->outreachMessages()->count());
    }

    public function test_manual_business_switches_to_email_flow_when_email_added(): void
    {
        $business = Business::create([
            'place_id' => 'p2', 'name' => 'Sabit Hat Ltd', 'phone' => '+902161112233', 'phone_type' => 'landline',
            'contact_channel' => 'manual', 'status' => 'manual_contact',
            'category_id' => $this->job->category_id, 'reviews' => [],
        ]);

        GenerateSiteJob::dispatchSync($business->id);
        $this->assertSame(0, $business->outreachMessages()->count(), 'Manuel kanalda mesaj taslağı oluşmaz');
        $this->assertNotNull($business->fresh()->site);
    }
}
