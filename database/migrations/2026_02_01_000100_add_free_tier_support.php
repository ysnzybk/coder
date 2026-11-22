<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ücretsiz kota sayaçları (Google Places aylık, ...)
        Schema::create('api_usage', function (Blueprint $table) {
            $table->id();
            $table->string('period', 10);   // 2026-11 (aylık) veya 2026-11-22 (günlük)
            $table->string('key', 60);
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['period', 'key']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('source', 20)->default('google')->after('place_id')->index(); // google | file | manual
            $table->string('social_url')->nullable()->after('website_raw');
            $table->decimal('source_confidence', 3, 2)->nullable()->after('source');
        });

        Schema::table('search_jobs', function (Blueprint $table) {
            $table->string('source', 20)->default('google')->after('id');
            $table->text('notice')->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('search_jobs', fn (Blueprint $t) => $t->dropColumn(['source', 'notice']));
        Schema::table('businesses', fn (Blueprint $t) => $t->dropColumn(['source', 'social_url', 'source_confidence']));
        Schema::dropIfExists('api_usage');
    }
};
