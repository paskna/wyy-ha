<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branding_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 80)->index();
            $table->string('filename');
            $table->string('storage_path');
            $table->string('mime_type', 120);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'fk_branding_assets_creator')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_assets');
    }
};
