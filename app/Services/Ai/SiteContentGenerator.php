<?php

namespace App\Services\Ai;

use App\Models\Business;

interface SiteContentGenerator
{
    /**
     * Site içeriğini şemaya uygun dizi olarak döndürür.
     *
     * @return array{
     *   hero_title: string, hero_subtitle: string, tagline: string, about: string,
     *   highlights: array<int, array{icon: string, title: string, text: string}>,
     *   services: array<int, array{name: string, description: string}>,
     *   review_summary: string, faq: array<int, array{q: string, a: string}>,
     *   cta_text: string, palette: string, font_style: string,
     *   seo_title: string, seo_description: string,
     *   image_prompts: array{hero: string, gallery_1: string, gallery_2: string}
     * }
     */
    public function generate(Business $business): array;
}
