<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('label', 100)->nullable();
            $table->string('password_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('disabled_by_admin_at')->nullable();
            $table->string('disabled_reason')->nullable();
            $table->unsignedInteger('access_version')->default(1);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->index(['timetable_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shares');
    }
};
