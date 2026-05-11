<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;
use App\Models\User;

new class extends Component
{
    public $pendingMahasiswa = 0;

    public function mount()
    {
        $this->pendingMahasiswa = User::where('role', 'mahasiswa')
            ->whereHas('mahasiswaProfile', function ($query) {
                $query->where('status_verifikasi', 'menunggu');
            })->count();
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $isMasterDataActive = request()->routeIs('admin.users.*', 'admin.mahasiswa.*', 'admin.merchant.*', 'admin.pemasok.*', 'admin.investor.*', 'admin.donatur.*');
    $isOperasionalActive = request()->routeIs('admin.monitoring.*', 'admin.distribusi.*', 'admin.po.*');
    $isKeuanganActive = request()->routeIs('admin.setoran.*', 'admin.bagihasil.*');
@endphp

<aside 
    x-data="{ sidebarOpen: true }"
    :class="sidebarOpen ? 'w-72' : 'w-20'"
    class="bg-[#1D6FD8] border-r border-[#1D6FD8] h-screen flex flex-col transition-all duration-300 ease-in-out relative hidden md:flex z-50 shadow-2xl text-white"
>

    {{-- Toggle Button --}}
    <button 
        @click="sidebarOpen = !sidebarOpen"
        class="absolute -right-3.5 top-9 bg-white border-2 border-[#1D6FD8] text-[#1D6FD8] rounded-full p-1.5 shadow-md hover:bg-gray-50 hover:scale-110 transition-all z-50 focus:outline-none"
    >
        <svg x-show="sidebarOpen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
        <svg x-show="!sidebarOpen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
    </button>

    {{-- Logo Area --}}
    <div class="h-20 flex items-center px-4 border-b border-white/10 overflow-hidden whitespace-nowrap bg-black/10 shrink-0">
        <div class="flex items-center gap-3 transition-all duration-300">
            <div class="p-2 bg-white rounded-xl flex-shrink-0 shadow-sm flex items-center justify-center w-10 h-10">
                <img src="{{ asset('images/logo-lapi.png') }}" alt="SCFS" class="h-6 w-6 object-contain">
            </div>
            <div x-show="sidebarOpen" x-transition.opacity.duration.300ms class="transition-opacity">
                <h1 class="font-black text-white text-xl tracking-tight">SCFS ADMIN</h1>
                <p class="text-xs text-blue-200 uppercase tracking-widest font-bold">LAPI ITB Panel</p>
            </div>
        </div>
    </div>

    {{-- Navigasi Menu (BISA DI-SCROLL) --}}
    <nav class="flex-1 min-h-0 px-3 py-6 space-y-2 overflow-y-auto overflow-x-hidden [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-white/20 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-white/40">

        <div x-show="sidebarOpen" x-transition class="px-4 mb-2 mt-2 text-xs font-bold text-blue-200 uppercase tracking-widest whitespace-nowrap">
            Menu Utama
        </div>

        {{-- Dashboard --}}
        <a href="{{ route('admin.dashboard') }}"
            class="flex items-center px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group whitespace-nowrap
            {{ request()->routeIs('admin.dashboard') 
                ? 'bg-white text-[#1D6FD8] shadow-lg' 
                : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            :class="sidebarOpen ? '' : 'justify-center'"
            title="Dashboard">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors
                {{ request()->routeIs('admin.dashboard') ? 'text-[#1D6FD8]' : 'text-blue-200 group-hover:text-white' }}"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z M14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z M4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z M14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span x-show="sidebarOpen" x-transition class="ml-3 transition-opacity duration-300">Dashboard</span>
        </a>

        {{-- Verifikasi Mahasiswa --}}
        <a href="{{ route('admin.verification') }}"
            class="relative flex items-center px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group whitespace-nowrap
            {{ request()->routeIs('admin.verification') 
                ? 'bg-white text-[#1D6FD8] shadow-lg' 
                : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            :class="sidebarOpen ? '' : 'justify-center'"
            title="Verifikasi Mahasiswa">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors
                {{ request()->routeIs('admin.verification') ? 'text-[#1D6FD8]' : 'text-blue-200 group-hover:text-white' }}"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-show="sidebarOpen" x-transition class="ml-3 transition-opacity duration-300">Verifikasi Mahasiswa</span>
            @if($pendingMahasiswa > 0)
                <span x-show="sidebarOpen" class="ml-auto bg-rose-500 text-white text-xs font-extrabold px-2 py-0.5 rounded-full shadow-sm animate-pulse">
                    {{ $pendingMahasiswa }}
                </span>
                <span x-show="!sidebarOpen" class="absolute top-2 right-2 w-3 h-3 bg-rose-500 rounded-full border-2 border-[#1D6FD8] animate-pulse"></span>
            @endif
        </a>

        {{-- SECTION: Master Data --}}
        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-xs font-bold text-blue-200 uppercase tracking-widest whitespace-nowrap border-t border-white/10 pt-4">
            Master Data
        </div>

        <div x-data="{ masterDataOpen: true }" class="mt-1">
            <button
                @click="if(!sidebarOpen) sidebarOpen = true; masterDataOpen = !masterDataOpen"
                class="w-full flex items-center justify-between px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group
                {{ $isMasterDataActive ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
                :class="sidebarOpen ? '' : 'justify-center'" title="Manajemen Pengguna">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isMasterDataActive ? 'text-white' : 'text-blue-200 group-hover:text-white' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Manajemen Pengguna</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': masterDataOpen}" class="w-4 h-4 transition-transform duration-300 {{ $isMasterDataActive ? 'text-white' : 'text-blue-200' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="masterDataOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 space-y-1 px-2 border-l-2 border-white/20 ml-4">
                <a href="{{ route('admin.users.index') }}" wire:navigate class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.users.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Akun Pengguna
                </a>
                <a href="{{ route('admin.mahasiswa.index') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.mahasiswa.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Data Mahasiswa
                </a>
                <a href="{{ route('admin.merchant.index') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.merchant.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Data Merchant
                </a>
                <a href="{{ route('admin.pemasok.index') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.pemasok.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Data Pemasok
                </a>
                <a href="{{ route('admin.investor.index') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.investor.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Data Investor
                </a>
                <a href="{{ route('admin.donatur.index') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.donatur.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Data Donatur
                </a>
            </div>
        </div>

        {{-- SECTION: Operasional --}}
        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-xs font-bold text-blue-200 uppercase tracking-widest whitespace-nowrap border-t border-white/10 pt-4">
            Operasional
        </div>

        <div x-data="{ operasionalOpen: true }" class="mt-1">
            <button @click="if(!sidebarOpen) sidebarOpen = true; operasionalOpen = !operasionalOpen"
                    class="w-full flex items-center justify-between px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group {{ $isOperasionalActive ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
                    :class="sidebarOpen ? '' : 'justify-center'" title="Operasional Transaksi">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isOperasionalActive ? 'text-white' : 'text-blue-200 group-hover:text-white' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Operasional Transaksi</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': operasionalOpen}" class="w-4 h-4 transition-transform duration-300 {{ $isOperasionalActive ? 'text-white' : 'text-blue-200' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="operasionalOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 space-y-1 px-2 border-l-2 border-white/20 ml-4">
                <a href="{{ route('admin.monitoring.index') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.monitoring.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Monitoring Transaksi
                </a>
                <a href="#" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.distribusi.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Distribusi Saldo
                </a>
                <a href="#" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.po.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    PO & Pendanaan
                </a>
            </div>
        </div>

        {{-- SECTION: Keuangan --}}
        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-xs font-bold text-blue-200 uppercase tracking-widest whitespace-nowrap border-t border-white/10 pt-4">
            Keuangan
        </div>

        <div x-data="{ keuanganOpen: true }" class="mt-1">
            <button @click="if(!sidebarOpen) sidebarOpen = true; keuanganOpen = !keuanganOpen"
                    class="w-full flex items-center justify-between px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group {{ $isKeuanganActive ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
                    :class="sidebarOpen ? '' : 'justify-center'" title="Keuangan & Settlement">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isKeuanganActive ? 'text-white' : 'text-blue-200 group-hover:text-white' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Keuangan & Settlement</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': keuanganOpen}" class="w-4 h-4 transition-transform duration-300 {{ $isKeuanganActive ? 'text-white' : 'text-blue-200' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="keuanganOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 space-y-1 px-2 border-l-2 border-white/20 ml-4">
                <a href="#" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.setoran.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Setoran Tunai
                </a>
                <a href="#" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('admin.bagihasil.*') ? 'text-[#1D6FD8] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-blue-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Riwayat Bagi Hasil
                </a>
            </div>
        </div>

        <div class="h-6"></div>
    </nav>

    {{-- User Profile & Logout (PASTI MUNCUL) --}}
    <div class="p-4 border-t border-white/10 bg-black/20 shrink-0">
        <div class="flex items-center gap-3 mb-4 px-1" :class="sidebarOpen ? '' : 'justify-center'">
            <div class="h-10 w-10 flex-shrink-0 rounded-full bg-white flex items-center justify-center text-[#1D6FD8] font-extrabold text-base shadow-md border-2 border-transparent relative">
                {{ substr(Auth::user()->name ?? 'A', 0, 2) }}
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-400 rounded-full border-2 border-[#1D6FD8]"></span>
            </div>
            <div x-show="sidebarOpen" x-transition class="overflow-hidden">
                <p class="text-[15px] font-extrabold text-white truncate">{{ Auth::user()->name ?? 'Admin LAPI' }}</p>
                <p class="text-xs text-blue-200 font-medium truncate w-32">{{ Auth::user()->email ?? 'admin@scfs.id' }}</p>
            </div>
        </div>

        <button wire:click="logout"
                class="w-full flex items-center px-3 py-3 text-[15px] font-bold text-white bg-rose-500/80 border border-rose-400/50 rounded-xl hover:bg-rose-500 transition-all duration-300 shadow-sm focus:outline-none"
                :class="sidebarOpen ? 'justify-center' : 'justify-center p-2 bg-transparent border-0 shadow-none hover:bg-white/10 hover:text-rose-400'"
                title="Keluar">
            <svg class="w-5 h-5 flex-shrink-0" :class="sidebarOpen ? 'mr-2.5' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span x-show="sidebarOpen" x-transition>Keluar Sistem</span>
        </button>
    </div>

</aside>