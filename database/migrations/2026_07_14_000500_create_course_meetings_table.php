<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40)->nullable();
            $table->string('teacher', 80)->nullable();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('location', 120)->nullable();
            $table->string('week_mode', 16)->default('all');
            $table->unsignedTinyInteger('start_week')->nullable();
            $table->unsignedTinyInteger('end_week')->nullable();
            $table->json('specific_weeks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'weekday', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_meetings');
    }
};
