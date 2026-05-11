<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantProfile extends Model
{
    // Tambahkan 4 kolom baru di bagian bawah array ini
    protected $fillable = [
        'user_id',
        'nama_kantin',
        'nama_pemilik',
        'no_hp',
        'nik',
        'info_pencairan',
        'lokasi_blok',
        'persentase_bagi_hasil',
        'status_toko',
        'saldo_token',
        'tagihan_setoran_tunai',
        'nama_bank',
        'no_rekening',
        
        // --- KOLOM ONBOARDING BARU ---
        'status_verifikasi',
        'foto_ktp',
        'foto_kantin',
        'catatan_penolakan',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}