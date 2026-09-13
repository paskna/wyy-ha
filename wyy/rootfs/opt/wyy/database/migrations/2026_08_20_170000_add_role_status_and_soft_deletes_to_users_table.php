<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(UserRole::User->value)->after('timezone')->index();
            $table->string('status')->default(UserStatus::Active->value)->after('role')->index();
            $table->timestamp('last_login_at')->nullable()->after('status')->index();
            $table->softDeletes();
        });

        DB::table('users')->whereNull('role')->update(['role' => UserRole::User->value]);
        DB::table('users')->whereNull('status')->update(['status' => UserStatus::Active->value]);

        $hasAdmin = DB::table('users')->where('role', UserRole::Admin->value)->where('status', UserStatus::Active->value)->exists();

        if (! $hasAdmin) {
            $firstUserId = DB::table('users')->orderBy('id')->value('id');

            if ($firstUserId) {
                DB::table('users')->where('id', $firstUserId)->update(['role' => UserRole::Admin->value]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'last_login_at', 'deleted_at']);
        });
    }
};
