<?php

namespace Tests\Feature;

use App\Services\Places\WebsiteClassifier;
use Tests\TestCase;

class WebsiteClassifierTest extends TestCase
{
    public function test_classifies_websites(): void
    {
        $c = new WebsiteClassifier;

        $this->assertSame('none', $c->classify(null));
        $this->assertSame('none', $c->classify(''));
        $this->assertSame('social_only', $c->classify('https://www.instagram.com/patidostu'));
        $this->assertSame('social_only', $c->classify('http://m.facebook.com/x'));
        $this->assertSame('social_only', $c->classify('linktr.ee/abc'));
        $this->assertSame('social_only', $c->classify('https://wa.me/905321234567'));
        $this->assertSame('real', $c->classify('https://patidostu.com.tr'));
        $this->assertSame('real', $c->classify('https://notinstagram.com'));
    }
}
