<?php

namespace App\Services\Images;

use RuntimeException;

/** Günlük ücretsiz neuron havuzu doldu; iş {secondsUntilReset} sonra (UTC 00:00) yeniden denenmeli. */
class CloudflareBudgetExceeded extends RuntimeException
{
    public function __construct(public readonly int $secondsUntilReset, string $message = 'Cloudflare günlük ücretsiz neuron bütçesi doldu.')
    {
        parent::__construct($message);
    }
}
