<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'order_id', 
        'user_id', 
        'merchant_id', 
        'sender_wallet_id',   // <--- PASTIKAN INI ADA
        'receiver_wallet_id', // <--- Tambahan baru
        'type', 
        'status', 
        'total_amount', 
        'fee_lkbb',     // <--- Tambahan baru
        'description'
    ];

    // Relasi ke pemilik transaksi
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi ke lawan transaksi (jika ada, misalnya Kantin ke Pemasok)
    public function relatedUser()
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function senderWallet()
    {
        return $this->belongsTo(Wallet::class, 'sender_wallet_id');
    }

    public function receiverWallet()
    {
        return $this->belongsTo(Wallet::class, 'receiver_wallet_id');
    }
}