<?php

namespace App\Services\Sites;

use App\Models\Business;
use App\Models\Site;
use Illuminate\Support\Str;

class SlugGenerator
{
    public function for(Business $business): string
    {
        $base = $this->slugify($business->name) ?: 'isletme';
        $candidates = [$base];

        if ($business->district) {
            $candidates[] = $base.'-'.$this->slugify($business->district->name);
        }

        foreach ($candidates as $candidate) {
            if ($this->isAvailable($candidate)) {
                return $candidate;
            }
        }

        $root = end($candidates);
        $i = 2;
        while (! $this->isAvailable("{$root}-{$i}")) {
            $i++;
        }

        return "{$root}-{$i}";
    }

    public function slugify(string $text): string
    {
        $map = ['ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g', 'ü' => 'u', 'Ü' => 'u', 'ş' => 's', 'Ş' => 's', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c'];

        return Str::limit(Str::slug(strtr($text, $map)), 60, '');
    }

    private function isAvailable(string $slug): bool
    {
        return ! in_array($slug, config('leadgen.reserved_slugs'), true)
            && ! Site::where('slug', $slug)->exists();
    }
}
