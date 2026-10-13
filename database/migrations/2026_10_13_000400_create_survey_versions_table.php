<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            // Question definitions: single_choice, rating, short_text, info.
            $table->json('schema_json');
            $table->string('privacy_notice_version', 64)->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['survey_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_versions');
    }
};
