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
        Schema::table('manifests', function (Blueprint $table) {
            $table->foreignId('origin_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('current_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifests', function (Blueprint $table) {
            $table->dropForeign(['origin_warehouse_id']);
            $table->dropForeign(['destination_warehouse_id']);
            $table->dropColumn(['origin_warehouse_id', 'destination_warehouse_id']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['current_warehouse_id']);
            $table->dropColumn('current_warehouse_id');
        });
    }
};
