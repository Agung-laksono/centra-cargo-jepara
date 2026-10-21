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
        Schema::create('manifests', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_type'); // PICKUP or DELIVERY
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vendor_name')->nullable();
            $table->string('vendor_resi')->nullable();
            $table->string('magic_link_token')->unique();
            $table->string('status')->default('PREPARING');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifests');
    }
};
