<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gender and sector get their own columns, the same way overall_rating and
 * issue_category do.
 *
 * The answers are already stored in answers_json, but a report that has to
 * group by them would otherwise have to parse that JSON in SQL. Two indexed
 * columns keep the grouping a plain query.
 *
 * No backfill: the questions are new, so every existing response is simply
 * "did not answer" until the survey starts asking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->string('gender', 32)->nullable()->index();
            $table->string('sector', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropIndex(['gender']);
            $table->dropIndex(['sector']);
            $table->dropColumn(['gender', 'sector']);
        });
    }
};
