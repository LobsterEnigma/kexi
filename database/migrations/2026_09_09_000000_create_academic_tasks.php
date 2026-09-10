<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('type', 20);
            $table->text('notes')->nullable();
            $table->string('url', 2000)->nullable();
            $table->string('location', 200)->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('reminder_minutes')->nullable();
            $table->dateTime('reminder_ack_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['timetable_id', 'completed_at', 'due_at']);
        });
        Schema::create('academic_task_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_task_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->string('location', 200)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->unsignedInteger('reminder_minutes')->nullable();
            $table->dateTime('reminder_ack_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['academic_task_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_task_entries');
        Schema::dropIfExists('academic_tasks');
    }
};
