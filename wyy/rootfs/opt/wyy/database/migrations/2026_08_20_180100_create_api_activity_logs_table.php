<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('action');
            $table->string('status')->index();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->foreignId('scan_id')->nullable()->constrained('scans', 'id', 'fk_api_logs_scan')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id', 'fk_api_logs_user')->nullOnDelete();
            $table->string('message')->nullable();
            $table->timestamps();

            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_activity_logs');
    }
};
