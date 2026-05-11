<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengajuanBantuan extends Model
{
    protected $fillable = [
        'mahasiswa_profile_id',
        'nominal',
        'status',
        'nomor_pengajuan',
    ];

    // Relasi balik ke profil mahasiswa
    public function mahasiswaProfile()
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_profile_id');
    }
}