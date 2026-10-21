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
        $tables = [
            'users',
            'warehouses',
            'drivers',
            'senders',
            'units',
            'manifests',
            'shipments',
            'settings',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('expedition_id')->nullable()->constrained('expeditions')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users',
            'warehouses',
            'drivers',
            'senders',
            'units',
            'manifests',
            'shipments',
            'settings',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['expedition_id']);
                $table->dropColumn('expedition_id');
            });
        }
    }
};
