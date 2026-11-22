<?php

namespace Tests\Feature;

use App\Jobs\SendOutreachEmailJob;
use App\Mail\OutreachMail;
use App\Models\Business;
use App\Models\OptOut;
use App\Models\Setting;
use App\Models\Site;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Outreach\MessageRenderer;
use App\Services\Outreach\OutreachService;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OutreachTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MessageTemplateSeeder::class);
    }

    private function business(string $channel = 'whatsapp'): Business
    {
        $b = Business::create([
            'place_id' => uniqid(), 'name' => 'Minik Patiler', 'phone' => '+905321112233', 'phone_type' => 'mobile',
            'email' => $channel === 'email' ? 'info@minik.test' : null,
            'contact_channel' => $channel, 'status' => 'site_ready',
        ]);
        Site::create(['business_id' => $b->id, 'slug' => 'minik-patiler-'.$b->id, 'template' => 'genel', 'content' => []]);

        return $b->load('site');
    }

    public function test_whatsapp_requires_approval_and_marks_sent(): void
    {
        $this->actingAs(User::factory()->create());
        $b = $this->business();
        $msg = app(MessageRenderer::class)->draft($b);

        $this->get(route('admin.whatsapp', $msg))->assertStatus(422);
        $this->assertSame('draft', $msg->fresh()->status);

        app(OutreachService::class)->approve($msg);
        $response = $this->get(route('admin.whatsapp', $msg));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/905321112233?text=', $response->headers->get('Location'));
        $this->assertSame('sent', $msg->fresh()->status);
        $this->assertSame(Business::STATUS_SENT, $b->fresh()->status);
    }

    public function test_whatsapp_route_requires_login(): void
    {
        $msg = app(MessageRenderer::class)->draft($this->business());
        $this->get(route('admin.whatsapp', $msg))->assertRedirect();
        $this->assertSame('draft', $msg->fresh()->status);
    }

    public function test_email_is_never_sent_without_approval(): void
    {
        Mail::fake();
        $msg = app(MessageRenderer::class)->draft($this->business('email'));

        SendOutreachEmailJob::dispatchSync($msg->id);
        Mail::assertNothingSent();

        app(OutreachService::class)->approve($msg);   // sync kuyrukta hemen gönderir
        Mail::assertSent(OutreachMail::class, fn ($m) => $m->hasTo('info@minik.test'));
        $this->assertSame('sent', $msg->fresh()->status);
        Mail::assertSent(OutreachMail::class, fn ($m) => str_contains($m->render(), 'https://test.local/t/c/'.$msg->token) && str_contains($m->render(), '/t/o/'.$msg->token.'.gif'));
    }

    public function test_email_rate_limit_holds_messages(): void
    {
        Mail::fake();
        Setting::put('mail_per_hour', 1);

        $first = app(MessageRenderer::class)->draft($this->business('email'));
        $second = app(MessageRenderer::class)->draft($this->business('email'));
        app(OutreachService::class)->approve($first);
        app(OutreachService::class)->approve($second);

        Mail::assertSentCount(1);
        $this->assertSame('sent', $first->fresh()->status);
        $this->assertSame('queued', $second->fresh()->status);
    }

    public function test_tracking_ignores_bots_and_counts_real_views(): void
    {
        $b = $this->business();
        $msg = app(MessageRenderer::class)->draft($b);
        app(OutreachService::class)->approve($msg);
        $msg->update(['status' => 'sent']);
        $b->update(['status' => 'sent']);

        // WhatsApp link önizleme botu
        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get('/'.$b->site->slug.'?r='.$msg->token)->assertOk();
        $this->assertSame(0, TrackingEvent::count());
        $this->assertSame('sent', $b->fresh()->status);

        $this->withHeader('User-Agent', self::BROWSER)->get('/'.$b->site->slug.'?r='.$msg->token)->assertOk();
        $this->assertSame(1, TrackingEvent::where('type', 'site_view')->where('outreach_message_id', $msg->id)->count());
        $this->assertSame(Business::STATUS_VIEWED, $b->fresh()->status);
    }

    public function test_email_open_and_click_tracking(): void
    {
        $b = $this->business('email');
        $msg = app(MessageRenderer::class)->draft($b);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) via ggpht.com GoogleImageProxy')
            ->get('/t/o/'.$msg->token.'.gif')->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertNotNull($msg->fresh()->first_opened_at);

        $this->withHeader('User-Agent', self::BROWSER)->get('/t/c/'.$msg->token)
            ->assertRedirect($b->site->url($msg->token));
        $this->assertNotNull($msg->fresh()->first_clicked_at);
    }

    public function test_opt_out_requires_confirmation_and_blocks_future_messages(): void
    {
        $b = $this->business();
        $msg = app(MessageRenderer::class)->draft($b);

        $this->get('/u/'.$msg->token)->assertOk()->assertSee('Onaylıyorum');
        $this->assertSame(0, OptOut::count(), 'GET ile (link önizleme) ret kaydedilmez');

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->post('/u/'.$msg->token)->assertOk()->assertSee('kaydedildi');

        $this->assertTrue(OptOut::matches('0532 111 22 33', null));
        $this->assertSame(Business::STATUS_OPTED_OUT, $b->fresh()->status);
        $this->assertSame('rejected', $msg->fresh()->status);
    }

    public function test_mark_won_creates_deal_and_monthly_payments(): void
    {
        $b = $this->business();
        $deal = app(OutreachService::class)->markWon($b, 15000, 1000, now()->subMonths(2)->toDateString());

        $this->assertSame(Business::STATUS_WON, $b->fresh()->status);
        $this->assertTrue($b->site->fresh()->is_sold);
        $this->assertSame(15000, $deal->payments()->where('type', 'setup')->value('amount'));

        $this->assertSame(1, app(OutreachService::class)->generateMaintenancePayments());
        $this->assertSame(0, app(OutreachService::class)->generateMaintenancePayments(), 'Aynı ay iki kez oluşturulmaz');

        $this->get('/'.$b->site->slug)->assertHeader('X-Robots-Tag', 'all');
    }

    public function test_reserved_paths_are_not_site_slugs(): void
    {
        $this->get('/admin')->assertRedirect();       // Filament login
        $this->get('/olmayan-firma')->assertNotFound();
    }
}
