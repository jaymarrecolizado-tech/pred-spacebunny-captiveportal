<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            // Null site_id = applies to every site in the survey's scope.
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('policy', 32)->default('optional');
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->index(['survey_id', 'site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_assignments');
    }
};
