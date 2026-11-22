<?php

namespace App\Services\Ai;

use App\Services\Sites\Themes;

/** Claude'un döndürmesi gereken site içeriği JSON şeması (structured outputs). */
class ContentSchema
{
    public static function schema(): array
    {
        $str = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'hero_title', 'hero_subtitle', 'tagline', 'about', 'highlights', 'services',
                'review_summary', 'faq', 'cta_text', 'palette', 'font_style',
                'seo_title', 'seo_description', 'image_prompts',
            ],
            'properties' => [
                'hero_title' => $str,
                'hero_subtitle' => $str,
                'tagline' => $str,
                'about' => $str,
                'highlights' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['icon', 'title', 'text'],
                        'properties' => ['icon' => $str, 'title' => $str, 'text' => $str],
                    ],
                ],
                'services' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'description'],
                        'properties' => ['name' => $str, 'description' => $str],
                    ],
                ],
                'review_summary' => $str,
                'faq' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['q', 'a'],
                        'properties' => ['q' => $str, 'a' => $str],
                    ],
                ],
                'cta_text' => $str,
                'palette' => ['type' => 'string', 'enum' => array_keys(Themes::PALETTES)],
                'font_style' => ['type' => 'string', 'enum' => array_keys(Themes::FONTS)],
                'seo_title' => $str,
                'seo_description' => $str,
                'image_prompts' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['hero', 'gallery_1', 'gallery_2'],
                    'properties' => ['hero' => $str, 'gallery_1' => $str, 'gallery_2' => $str],
                ],
            ],
        ];
    }

    /** Eksik/boş alanları güvenli varsayılanlarla tamamlar. */
    public static function normalize(array $content, array $fallback): array
    {
        foreach ($fallback as $key => $value) {
            if (! array_key_exists($key, $content) || $content[$key] === '' || $content[$key] === []) {
                $content[$key] = $value;
            }
        }

        if (! isset(Themes::PALETTES[$content['palette']])) {
            $content['palette'] = $fallback['palette'];
        }

        if (! isset(Themes::FONTS[$content['font_style']])) {
            $content['font_style'] = $fallback['font_style'];
        }

        $content['highlights'] = array_slice($content['highlights'], 0, 4);
        $content['services'] = array_slice($content['services'], 0, 8);
        $content['faq'] = array_slice($content['faq'], 0, 6);

        return $content;
    }
}
