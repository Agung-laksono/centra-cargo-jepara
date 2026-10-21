<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambahkan partnership_token pada tabel expeditions jika belum ada
        if (Schema::hasTable('expeditions') && !Schema::hasColumn('expeditions', 'partnership_token')) {
            Schema::table('expeditions', function (Blueprint $table) {
                $table->string('partnership_token', 64)->nullable()->unique()->after('domain_slug');
            });

            // Isi token unik untuk ekspedisi yang sudah ada
            $expeditions = DB::table('expeditions')->get();
            foreach ($expeditions as $exp) {
                $token = 'EXP-' . strtoupper(Str::random(8));
                DB::table('expeditions')->where('id', $exp->id)->update([
                    'partnership_token' => $token,
                ]);
            }
        }

        // 2. Tambahkan user_id pada tabel drivers untuk menghubungkan dengan akun login supir
        if (Schema::hasTable('drivers') && !Schema::hasColumn('drivers', 'user_id')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
            });
        }

        // 3. Buat tabel pivot kemitraan supir dengan ekspedisi resmi (driver_expeditions)
        if (!Schema::hasTable('driver_expeditions')) {
            Schema::create('driver_expeditions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('expedition_id')->index();
                $table->string('role', 30)->default('DRIVER_LINTAS');
                $table->string('status', 20)->default('ACTIVE'); // ACTIVE, PENDING, INACTIVE
                $table->string('handshake_code', 32)->nullable();
                $table->timestamp('partnered_at')->nullable();
                $table->timestamps();

                $table->unique(['driver_id', 'expedition_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_expeditions');

        if (Schema::hasTable('drivers') && Schema::hasColumn('drivers', 'user_id')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }

        if (Schema::hasTable('expeditions') && Schema::hasColumn('expeditions', 'partnership_token')) {
            Schema::table('expeditions', function (Blueprint $table) {
                $table->dropColumn('partnership_token');
            });
        }
    }
};
