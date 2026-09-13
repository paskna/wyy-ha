<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users', 'id', 'fk_audit_admin')->cascadeOnDelete()->index();
            $table->foreignId('target_user_id')->nullable()->constrained('users', 'id', 'fk_audit_target')->nullOnDelete()->index();
            $table->string('action')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['admin_user_id', 'target_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
