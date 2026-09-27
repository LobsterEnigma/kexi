<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('token_hash', 64)->unique();
            $table->text('token')->nullable();
            $table->json('contents');
            $table->date('date_from');
            $table->date('date_to');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('calendar_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timetable_id')->constrained()->cascadeOnDelete();
            $table->longText('payload');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        Schema::create('calendar_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timetable_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();
            $table->unique(['user_id', 'timetable_id', 'fingerprint'], 'calendar_import_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_import_items');
        Schema::dropIfExists('calendar_imports');
        Schema::dropIfExists('calendar_subscriptions');
    }
};
