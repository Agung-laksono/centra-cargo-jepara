<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('recipient_address')->nullable()->change();
            $table->string('destination_district')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('recipient_address')->nullable(false)->change();
            $table->string('destination_district')->nullable()->change();
        });
    }
};
