<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_taste_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('profile_version')->default(1)->after('user_id');
            $table->json('positive_profile_json')->nullable()->after('profile_json');
            $table->json('negative_profile_json')->nullable()->after('positive_profile_json');
            $table->json('grape_preferences_json')->nullable()->after('negative_profile_json');
            $table->json('region_preferences_json')->nullable()->after('grape_preferences_json');
            $table->unsignedInteger('sample_count')->default(0)->after('region_preferences_json');
            $table->decimal('confidence', 5, 4)->default(0)->after('sample_count');
            $table->timestamp('updated_at')->nullable()->after('calculated_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_taste_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'profile_version',
                'positive_profile_json',
                'negative_profile_json',
                'grape_preferences_json',
                'region_preferences_json',
                'sample_count',
                'confidence',
                'updated_at',
            ]);
        });
    }
};
