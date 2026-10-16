<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // When the controller last sent us a device trying to use this
            // location's Wi-Fi.
            //
            // The controller's API cannot report whether a location is pointed
            // at this platform, but the portal requests themselves can: if a
            // handoff has ever arrived, the location is pointed at us. That
            // makes "is this actually live?" a fact rather than a checkbox
            // somebody remembered to tick.
            $table->timestamp('portal_last_seen_at')
                ->nullable()
                ->after('survey_trigger_days');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('portal_last_seen_at');
        });
    }
};
