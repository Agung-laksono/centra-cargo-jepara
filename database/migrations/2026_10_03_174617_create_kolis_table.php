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
        Schema::create('kolis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->integer('koli_number');
            $table->integer('total_koli');
            $table->string('barcode_id')->unique();
            $table->string('description')->nullable();
            $table->string('photo_url')->nullable();
            $table->string('physical_status')->default('MENUNGGU_PICKUP');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kolis');
    }
};
