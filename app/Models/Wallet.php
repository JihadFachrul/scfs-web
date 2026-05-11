<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',             // Penanda ini dompet siapa (Mhs/Kantin/Pemasok/LKBB_DONATION, dll)
        'account_number',
        'pin',
        'balance',          // Saldo utama dompet
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Buku Besar.
     * Mengambil semua riwayat mutasi (masuk/keluar) dompet ini.
     */
    public function ledgerEntries()
    {
        return $this->hasMany(LedgerEntry::class)->latest();
    }
    
    // ==========================================
    // HELPER METHODS (Untuk Cek Tipe Dompet)
    // ==========================================

    // Cek apakah dompet ini adalah salah satu dari 3 dompet LKBB
    public function isLkbb() 
    { 
        return in_array($this->type, ['LKBB_DONATION', 'LKBB_INVESTMENT', 'LKBB_OPERATIONAL']); 
    }

    // Cek dompet LKBB secara spesifik
    public function isLkbbDonation() { return $this->type === 'LKBB_DONATION'; }
    public function isLkbbInvestment() { return $this->type === 'LKBB_INVESTMENT'; }
    public function isLkbbOperational() { return $this->type === 'LKBB_OPERATIONAL'; }

    // Cek tipe pengguna di ekosistem
    public function isMerchant() { return $this->type === 'MERCHANT'; }
    public function isPemasok() { return $this->type === 'PEMASOK'; }
    public function isMahasiswa() { return $this->type === 'MAHASISWA'; }
}