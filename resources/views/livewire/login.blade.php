<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

new 
#[Layout('components.layouts.landing')] // Menggunakan layout yang tadi kita buat
class extends Component {
    
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        session()->regenerate();

        return redirect()->intended('/dashboard'); 
    }
}; ?>

<div class="min-h-screen flex flex-col justify-between font-sans text-gray-800" style="background-color: #EEF2FF;"> 
    
    <header class="w-full max-w-7xl mx-auto px-6 py-8 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/logo-scfs.png') }}" class="h-15 w-auto mx-auto object-contain">
        </div>
        
        <div class="flex items-center space-x-8 text-lg font-medium">
            <a href="#" class="text-black border-b-2 border-black pb-1">Masuk</a>
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="text-gray-500 hover:text-blue-600 transition">Daftar</a>
            @endif
        </div>
    </header>

    <main class="flex-grow flex items-center justify-center px-4">
        <div class="bg-white rounded-[2rem] shadow-2xl overflow-hidden w-full max-w-6xl flex min-h-[650px]">
            
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
                        
                        <p class="text-gray-600 text-sm font-medium">Silahkan masukkan email dan kata sandi anda.</p>
                    </div>

                    <form wire:submit="login" class="space-y-6">
                        
                        <div class="space-y-2">
                            <label for="email" class="block text-sm font-bold text-gray-800">Email</label>
                            <input wire:model="email" type="email" id="email" placeholder="Masukkan Email" required autofocus
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition outline-none text-gray-700 placeholder-gray-400 text-sm">
                            @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="password" class="block text-sm font-bold text-gray-800">Kata Sandi</label>
                            <div class="relative" x-data="{ show: false }">
                                <input wire:model="password" :type="show ? 'text' : 'password'" id="password" placeholder="Masukkan Kata Sandi" required
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition outline-none text-gray-700 placeholder-gray-400 text-sm">
                                
                                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                    <svg x-show="show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5" style="display: none;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </button>
                            </div>
                            @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center justify-between mt-2">
                            <label class="flex items-center">
                                <input wire:model="remember" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 h-4 w-4">
                                <span class="ml-2 text-sm text-gray-700 font-medium">Ingat Saya</span>
                            </label>
                            
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm text-blue-500 hover:text-blue-700 font-medium">
                                    Lupa Kata Sandi?
                                </a>
                            @endif
                        </div>

                        <button type="submit" class="w-full py-3 bg-[#1e73be] hover:bg-blue-700 text-white font-bold rounded-lg transition shadow-md hover:shadow-lg mt-8 text-sm tracking-widest uppercase">
                            <span wire:loading.remove>MASUK</span>
                            <span wire:loading>Memproses...</span>
                        </button>

                    </form>
                </div>

            </div>
        </div>
    </main>

    <footer class="py-6 text-center text-sm text-gray-400 font-medium">
        &copy; 2026 Supply Chain Finance Service by PT LAPI ITB
    </footer>

</div>