#!/usr/bin/env bash
# Güncelleme betiği (cPanel / SSH). Kullanım: bash scripts/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"

echo "→ Bakım moduna alınıyor"
$PHP artisan down --retry=60 || true

echo "→ Kod güncelleniyor"
git pull --ff-only

echo "→ Bağımlılıklar"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "→ Veritabanı"
$PHP artisan migrate --force

echo "→ Önbellekler"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
$PHP artisan storage:link 2>/dev/null || true

echo "→ Kontrol"
$PHP artisan leadgen:check --production || { echo "⚠️  Kontrolde kritik sorun var (yukarıdaki tabloya bakın)"; }

$PHP artisan up
echo "✔ Tamamlandı"
