<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-device counters are kept only so the next sample can calculate a
        // safe positive delta. Raw MAC addresses are never stored; command uses
        // a keyed hash. Rows older than a week are deleted by the command.
        Schema::create('speed_client_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->string('mac_hash', 64);
            $table->unsignedBigInteger('traffic_down_bytes')->default(0);
            $table->unsignedBigInteger('traffic_up_bytes')->default(0);
            $table->index(['site_id', 'captured_at']);
            $table->index(['captured_at']);
        });

        // One row per site per sample. A null Mbps value means this was the first
        // stored sample or no comparable clients were present; it does NOT mean
        // zero traffic.
        Schema::create('speed_site_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->unsignedInteger('client_count')->default(0);
            $table->unsignedInteger('wireless_count')->default(0);
            $table->unsignedInteger('wired_count')->default(0);
            $table->unsignedBigInteger('ap_to_client_max_kbps')->default(0);
            $table->unsignedBigInteger('client_to_ap_max_kbps')->default(0);
            $table->unsignedInteger('low_rate_wireless_count')->default(0);
            $table->unsignedBigInteger('delta_down_bytes')->nullable();
            $table->unsignedBigInteger('delta_up_bytes')->nullable();
            $table->unsignedBigInteger('sample_interval_seconds')->nullable();
            $table->unsignedInteger('matched_clients')->default(0);
            $table->unsignedInteger('unmatched_clients')->default(0);
            $table->decimal('down_mbps', 12, 3)->nullable();
            $table->decimal('up_mbps', 12, 3)->nullable();
            $table->string('error', 512)->nullable();
            $table->index(['site_id', 'captured_at']);
            $table->index(['captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speed_site_samples');
        Schema::dropIfExists('speed_client_samples');
    }
};
