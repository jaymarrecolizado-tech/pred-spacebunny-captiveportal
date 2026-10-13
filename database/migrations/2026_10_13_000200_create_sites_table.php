<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            // FR-SITE-002/006: unique and immutable once created.
            $table->string('site_code', 64)->unique();
            $table->string('name');
            $table->string('region')->nullable()->index();
            $table->string('province')->nullable()->index();
            $table->string('municipality')->nullable()->index();
            $table->string('address_or_landmark')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('gateway_vendor')->nullable();
            $table->string('gateway_model')->nullable();
            $table->string('integration_mode', 32)->default('qr_code')->index();
            $table->string('integration_status', 32)->default('draft')->index();
            $table->string('status', 32)->default('active')->index();
            $table->date('commissioned_at')->nullable();
            $table->timestamp('last_integration_test_at')->nullable();
            $table->timestamps();
            // FR-SITE-007: disabling never deletes historical responses.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
