<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained();
            $table->foreignId('category_id')->constrained();
            $table->json('district_ids');
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('found_count')->default(0);
            $table->unsignedInteger('qualified_count')->default(0);
            $table->unsignedInteger('api_calls')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->unique();
            $table->foreignId('search_job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('phone_type')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('website_raw')->nullable();
            $table->string('website_status')->default('none');
            $table->string('contact_channel')->nullable()->index();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->json('reviews')->nullable();
            $table->json('types')->nullable();
            $table->json('opening_hours')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('maps_url')->nullable();
            $table->string('business_status')->nullable();
            $table->string('status')->default('discovered')->index();
            $table->string('disqualify_reason')->nullable();
            $table->string('call_outcome')->nullable();
            $table->text('call_note')->nullable();
            $table->unsignedInteger('setup_price')->nullable();
            $table->unsignedInteger('maintenance_price')->nullable();
            $table->text('last_error')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('template');
            $table->json('content')->nullable();
            $table->string('palette')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sold')->default(false);
            $table->string('custom_domain')->nullable()->unique();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('search_jobs');
    }
};
