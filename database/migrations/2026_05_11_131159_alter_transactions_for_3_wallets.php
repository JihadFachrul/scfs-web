<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Tambahkan kolom pelacakan dompet untuk Pengirim (Bisa null)
            $table->foreignId('sender_wallet_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('wallets')
                  ->nullOnDelete();

            // Tambahkan kolom pelacakan dompet untuk Penerima (Bisa null)
            $table->foreignId('receiver_wallet_id')
                  ->nullable()
                  ->after('sender_wallet_id')
                  ->constrained('wallets')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['sender_wallet_id']);
            $table->dropColumn('sender_wallet_id');

            $table->dropForeign(['receiver_wallet_id']);
            $table->dropColumn('receiver_wallet_id');
        });
    }
};