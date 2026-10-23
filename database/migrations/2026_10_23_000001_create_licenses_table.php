<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A license is one bot account: the bot signs in with product + username + password.
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username');
            $table->string('password');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->text('notes')->nullable();

            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('offline_notified_at')->nullable();
            $table->timestamp('expiry_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
