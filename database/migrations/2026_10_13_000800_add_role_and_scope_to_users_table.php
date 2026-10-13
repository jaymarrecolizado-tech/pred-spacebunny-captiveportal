<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('analyst')->index();
            $table->string('scope_type', 32)->default('all');
            $table->foreignId('scope_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            // Comma-separated province codes for province-scoped users.
            $table->string('scope_provinces', 512)->nullable();
            $table->boolean('is_active')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['scope_supplier_id']);
            $table->dropColumn(['role', 'scope_type', 'scope_supplier_id', 'scope_provinces', 'is_active']);
        });
    }
};
