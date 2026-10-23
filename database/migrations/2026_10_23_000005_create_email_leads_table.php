<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_leads', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32);
            $table->text('password')->nullable(); // encrypted at rest, exported on demand
            $table->string('type', 20)->index();
            $table->string('number_range', 100)->default('')->index();

            // Who supplied the lead. The username is a snapshot so the tag survives deleting the account.
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username', 64)->nullable()->index();
            $table->string('source', 10)->index(); // manual, import, api

            $table->timestamps();

            $table->index('created_at');
            $table->unique(['number', 'type', 'number_range']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_leads');
    }
};
