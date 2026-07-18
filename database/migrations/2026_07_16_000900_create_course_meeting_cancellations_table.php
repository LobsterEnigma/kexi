<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_meeting_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('week_number');
            $table->timestamps();

            $table->unique(['course_meeting_id', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_meeting_cancellations');
    }
};
