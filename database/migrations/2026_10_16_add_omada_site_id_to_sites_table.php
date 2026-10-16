<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Links a registry site to the controller-side site. Needed to pull
            // client counts and to configure the portal for that location.
            // Nullable: sites can be registered before the gateway knows them,
            // and the pilot site predates this column.
            $table->string('omada_site_id', 64)
                ->nullable()
                ->unique()
                ->after('site_code');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('omada_site_id');
        });
    }
};
