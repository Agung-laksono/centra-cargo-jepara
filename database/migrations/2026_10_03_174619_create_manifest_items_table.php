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
        Schema::create('manifest_items', function (Blueprint $table) {
            $table->foreignId('manifest_id')->constrained('manifests')->onDelete('cascade');
            $table->foreignId('koli_id')->constrained('kolis')->onDelete('cascade');
            $table->primary(['manifest_id', 'koli_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_items');
    }
};
