<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\MerchantProfile;
use App\Models\User;

new 
#[Layout('layouts.app')] 
class extends Component {
    use WithFileUploads;

    // State Profil & Bisnis
    public $nama_pemilik, $nama_kantin, $nik, $no_hp, $lokasi_blok;
    
    // State Rekening (Baru)
    public $nama_bank = 'BCA';
    public $bank_lainnya = '';
    public $no_rekening = '';

    public $foto_ktp_baru, $foto_kantin_baru;
    public $existing_ktp, $existing_kantin;
    
    // State Keamanan (Step-up Auth)
    public $password_konfirmasi = '';

    // State Ganti Password
    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';

    public function mount()
    {
        $profile = MerchantProfile::where('user_id', Auth::id())->firstOrFail();

        $this->nama_pemilik = $profile->nama_pemilik;
        $this->nama_kantin = $profile->nama_kantin;
        $this->nik = $profile->nik;
        $this->no_hp = $profile->no_hp;
        $this->lokasi_blok = $profile->lokasi_blok;

        // Logika untuk menampilkan Bank yang tersimpan
        $standardBanks = ['BCA', 'BNI', 'BRI', 'Mandiri', 'BJB', 'GoPay', 'OVO'];
        if (in_array($profile->nama_bank, $standardBanks)) {
            $this->nama_bank = $profile->nama_bank;
        } elseif (!empty($profile->nama_bank)) {
            $this->nama_bank = 'Lainnya';
            $this->bank_lainnya = $profile->nama_bank;
        }

        $this->no_rekening = $profile->no_rekening;

        $this->existing_ktp = $profile->foto_ktp;
        $this->existing_kantin = $profile->foto_kantin;
    }

    public function simpanProfil()
    {
        $this->validate([
            'nama_pemilik'   => 'required|string|max:255',
            'nama_kantin'    => 'required|string|max:255',
            'nik' => 'required|digits:16',
            'no_hp'          => 'required|string|max:20|regex:/^[0-9\-\+]+$/',
            'lokasi_blok'    => 'required|string|max:255',
            
            // Validasi Rekening Baru
            'nama_bank'      => 'required|string',
            'bank_lainnya'   => 'required_if:nama_bank,Lainnya',
            'no_rekening'    => 'required|numeric',

            'foto_ktp_baru'    => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto_kantin_baru' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'password_konfirmasi'=> 'required|string', 
        ], [
            'password_konfirmasi.required' => 'Password wajib diisi untuk keamanan perubahan rekening.',
            'bank_lainnya.required_if' => 'Nama bank wajib diisi jika memilih opsi Lainnya.',
            'no_rekening.numeric' => 'Nomor rekening hanya boleh berisi angka.'
        ]);

        $user = Auth::user();

        if (!Hash::check($this->password_konfirmasi, $user->password)) {
            throw ValidationException::withMessages([
                'password_konfirmasi' => 'Password yang Anda masukkan salah. Perubahan dibatalkan.',
            ]);
        }

        $profile = MerchantProfile::where('user_id', $user->id)->first();
        
        // Tentukan bank final yang akan disimpan ke database
        $bankFinal = $this->nama_bank === 'Lainnya' ? $this->bank_lainnya : $this->nama_bank;

        $updateData = [
            'nama_pemilik'   => $this->nama_pemilik,
            'nama_kantin'    => $this->nama_kantin,
            'nik'            => $this->nik,
            'no_hp'          => $this->no_hp,
            'lokasi_blok'    => $this->lokasi_blok,
            'nama_bank'      => $bankFinal,
            'no_rekening'    => $this->no_rekening,
        ];

        if ($this->foto_ktp_baru) {
            if ($profile->foto_ktp && Storage::disk('public')->exists($profile->foto_ktp)) {
                Storage::disk('public')->delete($profile->foto_ktp);
            }
            $updateData['foto_ktp'] = $this->foto_ktp_baru->store('merchants/ktp', 'public');
            $this->existing_ktp = $updateData['foto_ktp'];
        }

        if ($this->foto_kantin_baru) {
            if ($profile->foto_kantin && Storage::disk('public')->exists($profile->foto_kantin)) {
                Storage::disk('public')->delete($profile->foto_kantin);
            }
            $updateData['foto_kantin'] = $this->foto_kantin_baru->store('merchants/kantin', 'public');
            $this->existing_kantin = $updateData['foto_kantin'];
        }

        $profile->update($updateData);
        $user->update(['name' => $this->nama_pemilik]);

        $this->reset(['foto_ktp_baru', 'foto_kantin_baru', 'password_konfirmasi']);
        session()->flash('success_profil', 'Data profil dan rekening berhasil diamankan & disimpan.');
    }

    public function updatePassword()
    {
        $this->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed', 
        ], [
            'new_password.min' => 'Password baru minimal 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.'
        ]);

        $user = Auth::user();

        if (!Hash::check($this->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini salah.',
            ]);
        }

        $user->update([
            'password' => Hash::make($this->new_password)
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        session()->flash('success_password', 'Password akun Anda berhasil diperbarui!');
    }
}; ?>

<div class="py-8 px-6 md:px-8 w-full space-y-8 relative">
    
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Pengaturan Profil & Keamanan</h2>
        <p class="text-gray-500 text-sm mt-1">Kelola informasi rekening dan amankan akun toko Anda.</p>
    </div>

    {{-- BAGIAN 1: FORM PROFIL & REKENING --}}
    <form wire:submit.prevent="simpanProfil" class="space-y-6">
        
        @if(session('success_profil'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3 rounded-xl flex items-center gap-3 shadow-sm animate-pulse">
                <svg class="w-5 h-5 flex-shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="font-medium">{{ session('success_profil') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 xl:gap-8">
            
            {{-- KOLOM KIRI: INFO UTAMA & REKENING --}}
            <div class="lg:col-span-2 space-y-6">
                
                {{-- Card 1: Info Finansial (Telah Diperbarui) --}}
                <div class="bg-white rounded-2xl border border-rose-200 shadow-sm overflow-hidden relative">
                    <div class="absolute top-0 right-0 w-16 h-16 bg-rose-50 rounded-bl-full -mr-8 -mt-8 z-0"></div>
                    <div class="px-6 py-4 border-b border-rose-100 bg-rose-50/30 flex items-center gap-2 relative z-10">
                        <svg class="w-5 h-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        <h3 class="font-bold text-rose-900 text-sm">Informasi Pencairan Dana (Sensitif)</h3>
                    </div>
                    <div class="p-6 relative z-10">
                        <label class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-wider mb-2">Info Rekening Bank / E-Wallet</label>
                        
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="w-full sm:w-1/3">
                                <select wire:model.live="nama_bank" class="w-full py-3 px-4 text-sm font-bold text-gray-900 bg-white border border-rose-200 rounded-xl focus:border-rose-500 focus:ring-4 focus:ring-rose-100 transition">
                                    <option value="BCA">BCA</option>
                                    <option value="BNI">BNI</option>
                                    <option value="BRI">BRI</option>
                                    <option value="Mandiri">Mandiri</option>
                                    <option value="BJB">BJB</option>
                                    <option value="GoPay">GoPay</option>
                                    <option value="OVO">OVO</option>
                                    <option value="Lainnya">Bank Lainnya...</option>
                                </select>
                                @error('nama_bank') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror

                                @if($nama_bank === 'Lainnya')
                                    <input wire:model="bank_lainnya" type="text" placeholder="Ketik nama bank..." class="mt-3 w-full py-3 px-4 text-sm font-bold text-gray-900 bg-white border border-rose-200 rounded-xl focus:border-rose-500 focus:ring-4 focus:ring-rose-100 transition">
                                    @error('bank_lainnya') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
                                @endif
                            </div>

                            <div class="w-full sm:w-2/3">
                                <input wire:model="no_rekening" type="text" placeholder="Contoh: 1234567890 (Hanya Angka)" class="w-full py-3 px-4 text-sm font-bold text-gray-900 bg-white border border-rose-200 rounded-xl focus:border-rose-500 focus:ring-4 focus:ring-rose-100 transition">
                                @error('no_rekening') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <p class="text-[10px] text-gray-400 mt-3 italic">*Dana tarikan (Withdrawal) Anda akan ditransfer ke rekening ini. Jaga kerahasiaan akun Anda.</p>
                    </div>
                </div>

                {{-- Card 2: Info Bisnis --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        <h3 class="font-bold text-gray-900 text-sm">Informasi Kantin/Toko</h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nama Usaha</label>
                                <input wire:model="nama_kantin" type="text" class="w-full py-2.5 px-4 text-sm border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition">
                                @error('nama_kantin') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Lokasi / Blok</label>
                                <input wire:model="lokasi_blok" type="text" class="w-full py-2.5 px-4 text-sm border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition">
                                @error('lokasi_blok') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card 3: Info Pribadi --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        <h3 class="font-bold text-gray-900 text-sm">Informasi Pemilik</h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nama Pemilik Sesuai KTP</label>
                            <input wire:model="nama_pemilik" type="text" class="w-full py-2.5 px-4 text-sm border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition">
                            @error('nama_pemilik') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nomor Induk Kependudukan (NIK)</label>
                              <input 
                                    wire:model.defer="nik"
                                    type="text"
                                    maxlength="16"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,16)"
                                    class="w-full py-2.5 px-4 text-sm font-mono border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition"
                                >
                                @error('nik') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nomor WhatsApp Aktif</label>
                                <input wire:model="no_hp" type="text" class="w-full py-2.5 px-4 text-sm font-mono border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition">
                                @error('no_hp') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- KOLOM KANAN: DOKUMEN & ACTION --}}
            <div class="lg:col-span-1 space-y-6">
                
                {{-- Action Panel (SECURITY GATE) --}}
                <div class="bg-gray-900 rounded-2xl shadow-xl p-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-20 h-20 bg-gray-800 rounded-bl-full -mr-10 -mt-10 pointer-events-none"></div>
                    
                    <h3 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Otorisasi Keamanan
                    </h3>
                    
                    <div class="mb-5">
                        <label class="block text-[10px] font-bold text-gray-400 mb-1.5">Masukkan Password Akun Anda</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input wire:model="password_konfirmasi" type="password" placeholder="••••••••" 
                                class="w-full py-2.5 pl-9 pr-4 text-sm text-gray-900 bg-white border-0 rounded-xl focus:ring-4 focus:ring-emerald-500/50 transition">
                        </div>
                        @error('password_konfirmasi') <span class="text-rose-400 text-[10px] mt-1.5 font-bold block">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled" class="w-full py-3.5 text-sm font-extrabold text-white bg-emerald-600 rounded-xl hover:bg-emerald-500 transition shadow-lg shadow-emerald-900/50 flex justify-center items-center gap-2 focus:ring-4 focus:ring-emerald-500/50 disabled:opacity-50">
                        <span wire:loading.remove wire:target="simpanProfil">Simpan Perubahan</span>
                        <span wire:loading wire:target="simpanProfil">Memverifikasi...</span>
                    </button>
                    <p class="text-[9px] text-gray-500 mt-4 text-center leading-relaxed">
                        Verifikasi password diperlukan untuk mencegah pihak tidak bertanggung jawab mengubah nomor rekening pencairan Anda.
                    </p>
                </div>

                {{-- Dokumen KTP --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <h3 class="text-xs font-bold text-gray-900 mb-3">Dokumen KTP</h3>
                    
                    @if($existing_ktp)
                        <div class="mb-3 rounded-xl overflow-hidden border border-gray-100 shadow-sm relative group">
                            <img src="{{ asset('storage/' . $existing_ktp) }}" alt="KTP" class="w-full h-32 object-cover">
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <span class="text-white text-[10px] font-bold uppercase">KTP Saat Ini</span>
                            </div>
                        </div>
                    @endif

                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Unggah KTP Baru (Opsional)</label>
                    <input wire:model="foto_ktp_baru" type="file" accept="image/jpeg,image/png,image/jpg" class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                    <div wire:loading wire:target="foto_ktp_baru" class="text-[10px] font-bold text-emerald-600 mt-2 animate-pulse">Mengunggah...</div>
                    @error('foto_ktp_baru') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
                </div>

                {{-- Dokumen Kantin --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <h3 class="text-xs font-bold text-gray-900 mb-3">Foto Depan Kantin</h3>
                    
                    @if($existing_kantin)
                        <div class="mb-3 rounded-xl overflow-hidden border border-gray-100 shadow-sm relative group">
                            <img src="{{ asset('storage/' . $existing_kantin) }}" alt="Kantin" class="w-full h-32 object-cover">
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <span class="text-white text-[10px] font-bold uppercase">Foto Saat Ini</span>
                            </div>
                        </div>
                    @endif

                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Unggah Foto Baru (Opsional)</label>
                    <input wire:model="foto_kantin_baru" type="file" accept="image/jpeg,image/png,image/jpg" class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                    <div wire:loading wire:target="foto_kantin_baru" class="text-[10px] font-bold text-emerald-600 mt-2 animate-pulse">Mengunggah...</div>
                    @error('foto_kantin_baru') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
                </div>

            </div>
        </div>
    </form>

    <hr class="border-gray-200 my-8">

    {{-- BAGIAN 2: FORM GANTI PASSWORD --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden xl:w-2/3">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
            <h3 class="font-bold text-gray-900 text-sm">Ganti Password Akun</h3>
        </div>
        
        <form wire:submit.prevent="updatePassword" class="p-6 space-y-5">
            @if(session('success_password'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs px-3 py-2 rounded-lg flex items-center gap-2 mb-4">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="font-bold">{{ session('success_password') }}</span>
                </div>
            @endif

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Password Saat Ini</label>
                <input wire:model="current_password" type="password" class="w-full py-2.5 px-4 text-sm border border-gray-300 rounded-xl focus:border-gray-500 focus:ring-2 focus:ring-gray-200 transition">
                @error('current_password') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Password Baru</label>
                    <input wire:model="new_password" type="password" class="w-full py-2.5 px-4 text-sm border border-gray-300 rounded-xl focus:border-gray-500 focus:ring-2 focus:ring-gray-200 transition">
                    @error('new_password') <span class="text-rose-500 text-[10px] mt-1 font-bold block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Konfirmasi Password Baru</label>
                    <input wire:model="new_password_confirmation" type="password" class="w-full py-2.5 px-4 text-sm border border-gray-300 rounded-xl focus:border-gray-500 focus:ring-2 focus:ring-gray-200 transition">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 text-sm font-bold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition shadow-sm focus:ring-4 focus:ring-gray-100 disabled:opacity-50">
                    <span wire:loading.remove wire:target="updatePassword">Update Password</span>
                    <span wire:loading wire:target="updatePassword">Memproses...</span>
                </button>
            </div>
        </form>
    </div>

</div>