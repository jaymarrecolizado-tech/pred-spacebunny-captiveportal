<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The answer to "how was your experience" is a dropdown, so it gets its own
 * column like gender and sector do. That keeps the report able to count the
 * answers without parsing the answers blob.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_responses', function (Blueprint $table): void {
            $table->string('feedback', 32)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table): void {
            $table->dropIndex(['feedback']);
            $table->dropColumn('feedback');
        });
    }
};
