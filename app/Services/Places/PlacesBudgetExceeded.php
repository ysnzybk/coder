<?php

namespace App\Services\Places;

use RuntimeException;

/** Google Places aylık ücretsiz kota (uygulama içi güvenlik sınırı) doldu. */
class PlacesBudgetExceeded extends RuntimeException {}
