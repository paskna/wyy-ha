<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('wines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->constrained('producers', 'id', 'fk_wines_producer')->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('wine_type')->nullable()->index();
            $table->string('country')->nullable()->index();
            $table->string('region')->nullable()->index();
            $table->string('subregion')->nullable();
            $table->string('appellation')->nullable()->index();
            $table->string('classification')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['producer_id', 'normalized_name']);
        });

        Schema::create('wine_vintages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wine_id')->constrained('wines', 'id', 'fk_vintages_wine')->cascadeOnDelete()->index();
            $table->string('vintage')->nullable()->index();
            $table->decimal('alcohol', 4, 1)->nullable();
            $table->string('bottle_size')->nullable();
            $table->date('drinking_window_from')->nullable();
            $table->date('drinking_window_to')->nullable();
            $table->unsignedTinyInteger('serving_temperature_min')->nullable();
            $table->unsignedTinyInteger('serving_temperature_max')->nullable();
            $table->string('colour')->nullable();
            $table->decimal('body', 4, 3)->nullable();
            $table->decimal('tannin', 4, 3)->nullable();
            $table->decimal('acidity', 4, 3)->nullable();
            $table->decimal('sweetness', 4, 3)->nullable();
            $table->decimal('oak', 4, 3)->nullable();
            $table->decimal('fruit_intensity', 4, 3)->nullable();
            $table->decimal('mineral', 4, 3)->nullable();
            $table->decimal('earthy', 4, 3)->nullable();
            $table->decimal('spicy', 4, 3)->nullable();
            $table->decimal('floral', 4, 3)->nullable();
            $table->decimal('confidence_score', 4, 3)->nullable();
            $table->text('description')->nullable();
            $table->text('pairing_suggestions')->nullable();
            $table->timestamps();
            $table->unique(['wine_id', 'vintage']);
        });

        Schema::create('grape_varieties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        Schema::create('wine_vintage_grapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wine_vintage_id')->constrained('wine_vintages', 'id', 'fk_vintage_grapes_vintage')->cascadeOnDelete();
            $table->foreignId('grape_variety_id')->constrained('grape_varieties', 'id', 'fk_vintage_grapes_grape')->cascadeOnDelete();
            $table->unsignedTinyInteger('percentage')->nullable();
            $table->timestamps();
            $table->unique(['wine_vintage_id', 'grape_variety_id']);
        });

        Schema::create('wine_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wine_vintage_id')->constrained('wine_vintages', 'id', 'fk_wine_images_vintage')->cascadeOnDelete()->index();
            $table->foreignId('user_id')->constrained('users', 'id', 'fk_wine_images_user')->cascadeOnDelete()->index();
            $table->string('image_type')->index();
            $table->string('original_path');
            $table->string('optimized_path')->nullable();
            $table->string('label_path')->nullable();
            $table->string('perceptual_hash')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('user_wines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'id', 'fk_user_wines_user')->cascadeOnDelete()->index();
            $table->foreignId('wine_vintage_id')->constrained('wine_vintages', 'id', 'fk_user_wines_vintage')->cascadeOnDelete()->index();
            $table->string('preference')->default('unrated')->index();
            $table->text('personal_note')->nullable();
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->timestamp('first_scanned_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable()->index();
            $table->unsignedInteger('scan_count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'wine_vintage_id']);
        });

        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'id', 'fk_scans_user')->cascadeOnDelete()->index();
            $table->string('image_path');
            $table->text('ocr_text')->nullable();
            $table->string('status')->index();
            $table->decimal('recognition_confidence', 4, 3)->nullable();
            $table->foreignId('matched_wine_vintage_id')->nullable()->constrained('wine_vintages', 'id', 'fk_scans_vintage')->nullOnDelete()->index();
            $table->foreignId('matched_wine_id')->nullable()->constrained('wines', 'id', 'fk_scans_wine')->nullOnDelete()->index();
            $table->json('recognition_data_json')->nullable();
            $table->json('candidate_data_json')->nullable();
            $table->timestamps();
        });

        Schema::create('wine_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wine_vintage_id')->constrained('wine_vintages', 'id', 'fk_wine_sources_vintage')->cascadeOnDelete()->index();
            $table->string('source_type');
            $table->string('source_name');
            $table->string('source_url')->nullable();
            $table->timestamp('retrieved_at')->useCurrent();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->json('data_json')->nullable();
        });

        Schema::create('wine_taste_features', function (Blueprint $table) {
            $table->foreignId('wine_vintage_id')->primary()->constrained('wine_vintages', 'id', 'fk_taste_features_vintage')->cascadeOnDelete();
            $table->decimal('body', 4, 3)->nullable();
            $table->decimal('tannin', 4, 3)->nullable();
            $table->decimal('acidity', 4, 3)->nullable();
            $table->decimal('sweetness', 4, 3)->nullable();
            $table->decimal('oak', 4, 3)->nullable();
            $table->decimal('fruit', 4, 3)->nullable();
            $table->decimal('mineral', 4, 3)->nullable();
            $table->decimal('earth', 4, 3)->nullable();
            $table->decimal('spice', 4, 3)->nullable();
            $table->decimal('floral', 4, 3)->nullable();
            $table->decimal('freshness', 4, 3)->nullable();
            $table->decimal('ripeness', 4, 3)->nullable();
            $table->decimal('complexity', 4, 3)->nullable();
        });

        Schema::create('user_taste_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users', 'id', 'fk_taste_profiles_user')->cascadeOnDelete();
            $table->json('profile_json');
            $table->unsignedInteger('positive_sample_count')->default(0);
            $table->unsignedInteger('negative_sample_count')->default(0);
            $table->timestamp('calculated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_taste_profiles');
        Schema::dropIfExists('wine_taste_features');
        Schema::dropIfExists('wine_sources');
        Schema::dropIfExists('scans');
        Schema::dropIfExists('user_wines');
        Schema::dropIfExists('wine_images');
        Schema::dropIfExists('wine_vintage_grapes');
        Schema::dropIfExists('grape_varieties');
        Schema::dropIfExists('wine_vintages');
        Schema::dropIfExists('wines');
        Schema::dropIfExists('producers');
    }
};
