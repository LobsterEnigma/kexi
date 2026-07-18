<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('term_name', 100)->nullable();
            $table->date('term_start_date')->nullable();
            $table->unsignedTinyInteger('week_count')->default(18);
            $table->string('timezone', 64)->default('Asia/Shanghai');
            $table->unsignedTinyInteger('near_threshold_minutes')->default(30);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};
