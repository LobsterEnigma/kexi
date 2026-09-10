<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('category', 40)->default('生活');
            $table->string('color', 7)->default('#168575');
            $table->string('timezone', 64);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('all_day')->default(false);
            $table->string('location', 200)->nullable();
            $table->text('notes')->nullable();
            $table->string('repeat', 10)->default('none');
            $table->json('weekdays')->nullable();
            $table->date('repeat_until')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'starts_at']);
        });
        Schema::create('personal_event_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_event_id')->constrained()->cascadeOnDelete();
            $table->date('occurrence_date');
            $table->boolean('canceled')->default(false);
            $table->json('overrides')->nullable();
            $table->timestamps();
            $table->unique(['personal_event_id', 'occurrence_date']);
        });
        Schema::table('shares', fn (Blueprint $table) => $table->boolean('include_personal')->default(false));
    }

    public function down(): void
    {
        Schema::table('shares', fn (Blueprint $table) => $table->dropColumn('include_personal'));
        Schema::dropIfExists('personal_event_exceptions');
        Schema::dropIfExists('personal_events');
    }
};
