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
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke transaksi mana yang memicu perubahan saldo ini
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            
            // Relasi ke dompet siapa yang saldonya berubah
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            
            // Penanda apakah uang masuk (CREDIT) atau uang keluar (DEBIT)
            $table->string('entry_type'); 
            
            // Nominal uang yang masuk/keluar
            $table->decimal('amount', 15, 2);
            
            // Saldo akhir dompet SETELAH transaksi ini terjadi (Untuk Audit Trail)
            $table->decimal('balance_after', 15, 2);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};