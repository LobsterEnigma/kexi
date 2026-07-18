<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('review_started_at')->nullable()->after('ban_reason');
            $table->string('review_reason')->nullable()->after('review_started_at');
            $table->index('review_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['review_started_at']);
            $table->dropColumn(['review_started_at', 'review_reason']);
        });
    }
};
