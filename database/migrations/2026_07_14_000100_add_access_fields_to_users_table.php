<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->timestamp('banned_at')->nullable()->after('is_admin');
            $table->string('ban_reason')->nullable()->after('banned_at');
            $table->timestamp('sharing_disabled_at')->nullable()->after('ban_reason');
            $table->string('sharing_disabled_reason')->nullable()->after('sharing_disabled_at');
            $table->string('sharing_disabled_source', 16)->nullable()->after('sharing_disabled_reason');
            $table->unsignedInteger('auth_version')->default(1)->after('sharing_disabled_source');
            $table->index('banned_at');
            $table->index('sharing_disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['banned_at']);
            $table->dropIndex(['sharing_disabled_at']);
            $table->dropColumn([
                'is_admin',
                'banned_at',
                'ban_reason',
                'sharing_disabled_at',
                'sharing_disabled_reason',
                'sharing_disabled_source',
                'auth_version',
            ]);
        });
    }
};
