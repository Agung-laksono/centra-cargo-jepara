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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->foreignId('sender_id')->constrained('senders')->onDelete('cascade');
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->string('recipient_address');
            $table->string('destination_province');
            $table->string('destination_city');
            $table->string('destination_district')->nullable(); // Kecamatan
            $table->string('destination_village')->nullable(); // Kelurahan/Desa
            $table->string('payment_type')->default('COD'); // COD or PREPAID
            $table->decimal('goods_value', 15, 2)->default(0);
            $table->decimal('freight_fee', 15, 2)->default(0);
            $table->decimal('total_cod', 15, 2)->default(0);
            $table->string('payment_status')->default('UNPAID');
            $table->string('current_status')->default('DRAFT');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
