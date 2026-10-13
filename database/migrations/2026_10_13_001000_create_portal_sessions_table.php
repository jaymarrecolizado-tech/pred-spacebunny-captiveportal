<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_sessions', function (Blueprint $table) {
            // Opaque, non-sequential public reference handed to the gateway.
            $table->uuid('id')->primary();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // Single-use nonce: a token can be replayed against the same session
            // only if this value is present and unused (Spec 9.3).
            $table->string('nonce', 64)->unique();
            $table->string('status', 32)->default('active')->index();
            // dateTime rather than timestamp: MariaDB gives the first non-null TIMESTAMP an
            // implicit default, which collides with a second required one.
            $table->dateTime('issued_at');
            $table->dateTime('expires_at')->index();
            $table->dateTime('completed_at')->nullable();
            $table->uuid('response_id')->nullable();
            // Gateway-supplied session reference, hashed. Never stored raw.
            $table->string('gateway_session_hash', 64)->nullable()->index();
            // Hashed client IP: enough to spot abuse without retaining the address.
            $table->string('client_ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['integration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_sessions');
    }
};
