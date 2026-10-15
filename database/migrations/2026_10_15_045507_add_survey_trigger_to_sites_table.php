<?php

use App\Enums\SurveyTriggerMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Defaults preserve today's behaviour: every connection is asked, so
            // adding this to a live site changes nothing until someone opts in.
            $table->string('survey_trigger_mode', 32)
                ->default(SurveyTriggerMode::Always->value)
                ->after('integration_status')
                ->index();

            // Only read when the mode is Interval.
            $table->unsignedSmallInteger('survey_trigger_days')
                ->default(7)
                ->after('survey_trigger_mode');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['survey_trigger_mode', 'survey_trigger_days']);
        });
    }
};
