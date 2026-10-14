<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the gateway wants the browser sent once the survey is done.
     *
     * A gateway hands a client over and needs it back: it holds the session
     * that has to be released. Without this the user finishes the survey and is
     * stranded on our thank-you page, still unauthenticated.
     *
     * Stored server-side and settable only by an authenticated gateway, so a
     * browser cannot choose its own destination.
     */
    public function up(): void
    {
        Schema::table('portal_sessions', function (Blueprint $table) {
            $table->string('redirect_url', 2048)->nullable()->after('response_id');
        });
    }

    public function down(): void
    {
        Schema::table('portal_sessions', function (Blueprint $table) {
            $table->dropColumn('redirect_url');
        });
    }
};
