<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Deal;
use App\Models\District;
use App\Models\MessageTemplate;
use App\Models\OptOut;
use App\Models\OutreachMessage;
use App\Models\Payment;
use App\Models\Province;
use App\Models\SearchJob;
use App\Models\Site;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Outreach\MessageRenderer;
use Database\Seeders\CategorySeeder;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Her admin sayfasını DOLU veriyle açar. Boş tabloda görünmeyen hatalar (ör. Filament closure
 * parametre adları, null ilişkiler) yalnızca kayıt varken ortaya çıkar.
 */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private OutreachMessage $message;

    private Deal $deal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, MessageTemplateSeeder::class]);

        $province = Province::create(['plate_code' => 34, 'name' => 'İstanbul', 'slug' => 'istanbul']);
        $district = District::create(['province_id' => $province->id, 'name' => 'Kadıköy', 'slug' => 'kadikoy']);
        $category = Category::where('slug', 'pet-shop')->firstOrFail();

        $job = SearchJob::create(['province_id' => $province->id, 'category_id' => $category->id, 'district_ids' => [$district->id], 'status' => 'partial', 'notice' => 'Kota']);

        $this->business = Business::create([
            'place_id' => 'p1', 'search_job_id' => $job->id, 'name' => 'Pati Dostu', 'phone' => '+905321112233', 'phone_type' => 'mobile',
            'contact_channel' => 'whatsapp', 'status' => 'sent', 'category_id' => $category->id, 'province_id' => $province->id,
            'district_id' => $district->id, 'rating' => 4.8, 'review_count' => 12, 'reviews' => [], 'last_error' => 'örnek hata',
        ]);
        Business::create(['place_id' => 'f1', 'source' => 'file', 'name' => 'Dosya Firması', 'phone' => '+902161112233', 'phone_type' => 'landline',
            'contact_channel' => 'manual', 'status' => 'needs_verification', 'category_id' => $category->id, 'province_id' => $province->id, 'district_id' => $district->id]);
        Business::create(['place_id' => 'm1', 'name' => 'Manuel', 'status' => 'manual_contact', 'contact_channel' => 'manual']);
        Business::create(['place_id' => 'd1', 'name' => 'Elenen', 'status' => 'disqualified', 'disqualify_reason' => 'Web sitesi var']);

        Site::create(['business_id' => $this->business->id, 'slug' => 'pati-dostu', 'template' => 'petshop', 'content' => [
            'hero_title' => 'Pati Dostu', 'services' => [['name' => 'Mama', 'description' => 'Kedi maması']],
        ], 'palette' => 'orman']);

        $this->message = app(MessageRenderer::class)->draft($this->business->load('site', 'district', 'province', 'category'));
        $this->message->update(['status' => 'sent', 'sent_at' => now()]);
        app(MessageRenderer::class)->draft(Business::find(2)); // taslak (kanal manuel → alıcı boş olabilir)

        TrackingEvent::create(['business_id' => $this->business->id, 'outreach_message_id' => $this->message->id, 'type' => 'site_view', 'channel' => 'whatsapp']);

        $this->deal = Deal::create(['business_id' => $this->business->id, 'setup_price' => 15000, 'maintenance_price' => 1000, 'started_at' => now()->subMonths(2)]);
        Payment::create(['deal_id' => $this->deal->id, 'type' => 'setup', 'amount' => 15000, 'due_date' => now()->subMonths(2)]);
        Payment::create(['deal_id' => $this->deal->id, 'type' => 'maintenance', 'period' => now()->format('Y-m'), 'amount' => 1000, 'due_date' => now()]);

        OptOut::create(['value' => '+905550000000', 'type' => 'phone', 'source' => 'link']);

        $this->actingAs(User::factory()->create());
    }

    public function test_every_admin_page_renders_with_data(): void
    {
        $pages = [
            '/admin', '/admin/search-jobs', '/admin/search-jobs/create',
            '/admin/businesses', '/admin/businesses/'.$this->business->id.'/edit',
            '/admin/outreach-messages', '/admin/outreach-messages/'.$this->message->id.'/edit',
            '/admin/deals', '/admin/deals/'.$this->deal->id.'/edit',
            '/admin/categories', '/admin/categories/create', '/admin/categories/'.Category::first()->id.'/edit',
            '/admin/message-templates', '/admin/message-templates/create', '/admin/message-templates/'.MessageTemplate::first()->id.'/edit',
            '/admin/opt-outs', '/admin/opt-outs/create', '/admin/settings', '/admin/import-places',
        ];

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_every_business_and_message_tab_renders(): void
    {
        foreach (['active', 'verify', 'manual', 'sent', 'won', 'closed', 'disqualified', 'failed', 'all'] as $tab) {
            $this->get('/admin/businesses?activeTab='.$tab)->assertOk();
        }

        foreach (['draft', 'to_send', 'queued', 'sent', 'all'] as $tab) {
            $this->get('/admin/outreach-messages?activeTab='.$tab)->assertOk();
        }
    }
}
