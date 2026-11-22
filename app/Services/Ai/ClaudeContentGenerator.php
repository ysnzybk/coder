<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Models\Business;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Claude API ile firmaya özel site içeriği üretir (ücretli, isteğe bağlı sağlayıcı).
 * Structured outputs → şemaya uygun JSON. Claude reddederse şablon içeriğe düşer.
 */
class ClaudeContentGenerator implements SiteContentGenerator
{
    public function __construct(
        private readonly Client $client,
        private readonly TemplateContentGenerator $fallback,
        private readonly ContentSanitizer $sanitizer,
    ) {}

    public function generate(Business $business): array
    {
        $fallback = $this->fallback->generate($business);

        $message = $this->client->messages->create(
            model: config('leadgen.anthropic.model'),
            maxTokens: config('leadgen.anthropic.max_tokens'),
            system: SitePrompt::system(),
            messages: [['role' => 'user', 'content' => SitePrompt::brief($business)]],
            outputConfig: [
                'effort' => config('leadgen.anthropic.effort'),
                'format' => ['type' => 'json_schema', 'schema' => ContentSchema::schema()],
            ],
        );

        if ($message->stopReason === 'refusal') {
            Log::warning('Claude içerik üretimini reddetti, şablon içerik kullanılıyor.', ['business' => $business->id]);

            return $fallback;
        }

        if ($message->stopReason === 'max_tokens') {
            throw new RuntimeException('Claude yanıtı max_tokens sınırında kesildi.');
        }

        $json = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $json = json_decode($block->text, true);
                break;
            }
        }

        if (! is_array($json)) {
            throw new RuntimeException('Claude geçerli JSON döndürmedi.');
        }

        return ['_source' => 'claude'] + ContentSchema::normalize($this->sanitizer->clean($json), array_diff_key($fallback, ['_source' => true]));
    }
}
