<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            // Non-sequential public identifier (Spec 10.7).
            $table->uuid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            // Snapshot of the supplier at submission time for historical reporting.
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('survey_version_id')->constrained()->restrictOnDelete();
            $table->string('attribution_method', 32)->index();
            // Hashed only. Raw portal tokens are never stored (Spec 10.7).
            $table->string('session_reference_hash', 64)->nullable()->index();
            // FR-RESP-004: unique so a retried request cannot double-write.
            $table->string('idempotency_key_hash', 64)->nullable()->unique();
            $table->json('answers_json');
            $table->unsignedTinyInteger('overall_rating')->nullable()->index();
            $table->string('issue_category', 64)->nullable()->index();
            $table->timestamp('submitted_at')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
