<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            // Null only for an approved supplier-level integration pattern.
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('integration_type', 32)->default('portal_handoff');
            $table->string('public_identifier', 64)->unique();
            // Encrypted at rest (Laravel encrypted cast). Never returned by
            // ordinary API responses and never written to logs (Spec 10.3).
            $table->text('secret');
            $table->string('status', 32)->default('active')->index();
            // FR-CAP-007: a test integration can never authorize production access.
            $table->boolean('is_test_mode')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->timestamps();
            $table->timestamp('revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
