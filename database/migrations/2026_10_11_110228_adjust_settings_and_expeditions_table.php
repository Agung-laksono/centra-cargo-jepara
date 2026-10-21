<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expeditions', function (Blueprint $table) {
            $table->string('receipt_code', 10)->nullable()->after('domain_slug');
            $table->string('tagline')->nullable()->after('name');
            $table->string('theme_color', 20)->default('#8B5A2B')->after('is_active');
            $table->string('logo_url')->nullable()->after('theme_color');
        });

        // Di SQLite, untuk mengubah index unik key kita re-create / sesuaikan
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['key', 'expedition_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key', 'expedition_id']);
            $table->unique(['key']);
        });

        Schema::table('expeditions', function (Blueprint $table) {
            $table->dropColumn(['receipt_code', 'tagline', 'theme_color', 'logo_url']);
        });
    }
};
