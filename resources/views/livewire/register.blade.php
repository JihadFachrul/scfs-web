<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;

// Import semua model profil agar bisa dibuatkan profil kosong saat daftar
use App\Models\MahasiswaProfile;
use App\Models\MerchantProfile;
use App\Models\PemasokProfile;
use App\Models\InvestorProfile;
use App\Models\DonaturProfile; // Tambahan import Donatur

new 
#[Layout('components.layouts.landing')] 
class extends Component {
    
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|email|max:255|unique:users')]
    public string $email = '';

    // Tambahan Validasi untuk Role (donatur dimasukkan)
    #[Validate('required|in:mahasiswa,merchant,pemasok,investor,donatur')]
    public string $role = 'mahasiswa'; // Default pilihan

    #[Validate('required|string|min:8|confirmed')] 
    public string $password = '';

    #[Validate('required|string|min:8')]
    public string $password_confirmation = '';

    public function register()
    {
        $this->validate();

        // 1. Buat Akun User
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => $this->role, // Mengambil role dari pilihan dropdown
        ]);

        // 2. Buat Profil Kosong sesuai Role agar langsung muncul di halaman Admin
        if ($this->role === 'mahasiswa') {
            MahasiswaProfile::create(['user_id' => $user->id]);
        } elseif ($this->role === 'merchant') {
            MerchantProfile::create(['user_id' => $user->id, 'nama_kantin' => $this->name . ' (Baru)', 'nama_pemilik' => $this->name]);
        } elseif ($this->role === 'pemasok') {
            PemasokProfile::create(['user_id' => $user->id, 'nama_perusahaan' => 'PT/Toko ' . $this->name, 'nama_pic' => $this->name, 'kategori_barang' => 'Lainnya']);
        } elseif ($this->role === 'investor') {
            InvestorProfile::create(['user_id' => $user->id, 'nama_lengkap' => $this->name]);
        } elseif ($this->role === 'donatur') {
            DonaturProfile::create(['user_id' => $user->id, 'nama_lengkap' => $this->name]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended('/dashboard');
    }
}; ?>

<div class="min-h-screen flex flex-col justify-between font-sans text-gray-800" style="background-color: #EEF2FF;">
    
    <header class="w-full max-w-7xl mx-auto px-6 py-8 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/logo-scfs.png') }}" class="h-15 w-auto mx-auto object-contain">
        </div>
        
        <div class="flex items-center space-x-8 text-lg font-medium">
            <a href="{{ route('login') }}" class="text-gray-500 hover:text-blue-600 transition">Masuk</a>
            <span class="text-black border-b-2 border-black pb-1 cursor-default">Daftar</span>
        </div>
    </header>

    <main class="flex-grow flex items-center justify-center px-4 py-8">
        <div class="bg-white rounded-[2rem] shadow-2xl overflow-hidden w-full max-w-6xl flex min-h-[700px]">
            
            <div class="hidden lg:block w-1/2 relative overflow-hidden">
                <img src="{{ asset('images/gambar.jpg') }}" 
                    alt="Gambar Latar"
                    class="absolute inset-0 w-full h-full object-cover object-center">
    
                <div class="absolute inset-0 bg-black/10"></div>
            </div>

            <div class="w-full lg:w-1/2 p-12 lg:p-16 flex flex-col justify-center bg-white relative">
                
                <div class="max-w-md mx-auto w-full">
                    <div class="text-center mb-8">
                        <img src="{{ asset('images/logo-scfs.png') }}" class="h-20 w-auto mx-auto object-contain">
                        <h2 class="text-2xl font-bold text-gray-900">Buat Akun Baru</h2>
                        <p class="text-gray-600 text-sm mt-2">Silahkan lengkapi data diri anda.</p>
                    </div>

                    <form wire:submit="register" class="space-y-5">
                        
                        <div class="space-y-2">
                            <label for="name" class="block text-sm font-bold text-gray-800">Nama Lengkap / Instansi</label>
                            <input wire:model="name" type="text" id="name" placeholder="Masukkan Nama Anda" required autofocus
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition outline-none text-gray-700 placeholder-gray-400 text-sm">
                            @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="email" class="block text-sm font-bold text-gray-800">Email Aktif</label>
                            <input wire:model="email" type="email" id="email" placeholder="Masukkan Email" required
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition outline-none text-gray-700 placeholder-gray-400 text-sm">
                            @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="role" class="block text-sm font-bold text-gray-800">Daftar Sebagai (Peran)</label>
                            <select wire:model="role" id="role" required
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition outline-none text-gray-700 bg-white text-sm cursor-pointer">
                                <option value="mahasiswa">Mahasiswa (Penerima Bantuan)</option>
                                <option value="merchant">Pemilik Kantin / Warung</option>
                                <option value="pemasok">Mitra Pemasok / Supplier</option>
                                <option value="investor">Investor (Pendana PO)</option>
                                <option value="donatur">Donatur (Pemberi Bantuan)</option>
                            </select>
                            @error('role') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="password" class="block text-sm font-bold text-gray-800">Kata Sandi</label>
                            <div class="relative" x-data="{ show: false }">
                                <input wire:model="password" :type="show ? 'text' : 'password'" id="password" placeholder="Masukkan Kata Sandi" required
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition outline-none text-gray-700 placeholder-gray-400 text-sm">
                                
                                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                </button>
                            </div>
                            @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="password_confirmation" class="block text-sm font-bold text-gray-800">Konfirmasi Kata Sandi</label>
                            <div class="relative" x-data="{ show: false }">
                                <input wire:model="password_confirmation" :type="show ? 'text' : 'password'" id="password_confirmation" placeholder="Ulangi Kata Sandi" required
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition outline-none text-gray-700 placeholder-gray-400 text-sm">
                                
                                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="w-full py-3 bg-[#1e73be] hover:bg-blue-700 text-white font-bold rounded-lg transition shadow-md hover:shadow-lg mt-8 text-sm tracking-widest uppercase">
                            <span wire:loading.remove>DAFTAR SEKARANG</span>
                            <span wire:loading>Memproses...</span>
                        </button>
                        
                        <div class="text-center mt-4">
                            <span class="text-sm text-gray-600">Sudah punya akun? </span>
                            <a href="{{ route('login') }}" class="text-sm font-bold text-[#1e73be] hover:underline">Masuk sekarang</a>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </main>

    <footer class="py-6 text-center text-sm text-gray-400 font-medium">
        &copy; {{ date('Y') }} Supply Chain Finance Service by PT LAPI ITB
    </footer>

</div>