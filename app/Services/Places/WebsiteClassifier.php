<?php

namespace App\Services\Places;

use App\Models\Business;

class WebsiteClassifier
{
    /**
     * none        → website alanı boş
     * social_only → sadece sosyal medya / link servisi (Instagram, Facebook, Linktree…)
     * real        → gerçek bir web sitesi var (hedef dışı)
     */
    public function classify(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return Business::WEBSITE_NONE;
        }

        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

        if (! $host) {
            return Business::WEBSITE_NONE;
        }

        $host = mb_strtolower(preg_replace('/^www\./i', '', $host));

        foreach (config('leadgen.social_domains') as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return Business::WEBSITE_SOCIAL_ONLY;
            }
        }

        return Business::WEBSITE_REAL;
    }
}
