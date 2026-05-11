<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_profiles', function (Blueprint $table) {
            // Menambahkan kolom nama_bank dan no_rekening
            $table->string('nama_bank')->nullable()->after('lokasi_blok');
            $table->string('no_rekening')->nullable()->after('nama_bank');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_profiles', function (Blueprint $table) {
            // Menghapus kolom jika di-rollback
            $table->dropColumn(['nama_bank', 'no_rekening']);
        });
    }
};