<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('passkey_handle', 64)->nullable()->unique();
        });
        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->char('credential_hash', 64)->unique();
            $table->text('credential_id');
            $table->text('public_key');
            $table->string('rp_id', 253);
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('passkey_challenges', function (Blueprint $table) {
            $table->char('id', 64)->primary();
            $table->char('session_hash', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('purpose', 16);
            $table->text('payload');
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkey_challenges');
        Schema::dropIfExists('passkeys');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('passkey_handle'));
    }
};
