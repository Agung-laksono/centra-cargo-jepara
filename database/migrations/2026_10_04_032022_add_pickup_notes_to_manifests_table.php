<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manifests', function (Blueprint $table) {
            // Apakah penjemputan di-paksa-selesai tanpa verifikasi penuh
            $table->boolean('is_force_completed')->default(false)->after('status');
            // Berapa koli yang benar-benar ter-scan oleh supir
            $table->integer('scanned_koli_count')->default(0)->after('is_force_completed');
            // Catatan tambahan (alasan paksa selesai, dll)
            $table->text('pickup_notes')->nullable()->after('scanned_koli_count');
        });
    }

    public function down(): void
    {
        Schema::table('manifests', function (Blueprint $table) {
            $table->dropColumn(['is_force_completed', 'scanned_koli_count', 'pickup_notes']);
        });
    }
};
