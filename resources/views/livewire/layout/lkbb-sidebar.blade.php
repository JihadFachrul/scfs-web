<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    // Logika untuk mendeteksi menu mana yang sedang aktif
    $isTokenActive = request()->routeIs('lkbb.injeksi-saldo', 'lkbb.riwayat-injeksi');
    $isApprovalActive = request()->routeIs('approval.*', 'supply-chain.approval');
    $isMasterDompetActive = request()->is('keuangan/mahasiswa*', 'keuangan/merchant*', 'keuangan/pemasok*') || request()->routeIs('saldo.bantuan');
    $isOperasionalActive = request()->routeIs('supply-chain.create', 'supply-chain.bills', 'lkbb.scf.approval');
    $isSetoranActive = request()->routeIs('keuangan.penagihan', 'keuangan.riwayat-fee');
    $isKeuanganActive = request()->is('keuangan/pencairan*') || request()->routeIs('lkbb.wallets', 'lkbb.withdraw.merchant.approval', 'lkbb.withdraw.pemasok.approval');
@endphp

<aside 
    x-data="{ sidebarOpen: true }"
    :class="sidebarOpen ? 'w-72' : 'w-20'"
    class="bg-[#4338CA] border-r border-[#4338CA] h-screen flex flex-col transition-all duration-300 ease-in-out relative hidden md:flex z-50 shadow-2xl text-white"
>
    
    {{-- Toggle Button --}}
    <button 
        @click="sidebarOpen = !sidebarOpen"
        class="absolute -right-3.5 top-9 bg-white border-2 border-[#4338CA] text-[#4338CA] rounded-full p-1.5 shadow-md hover:bg-gray-50 hover:scale-110 transition-all z-50 focus:outline-none"
    >
        <svg x-show="sidebarOpen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
        <svg x-show="!sidebarOpen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
    </button>

    {{-- Logo Area --}}
    <div class="h-20 flex items-center px-4 border-b border-white/10 overflow-hidden whitespace-nowrap bg-black/10 shrink-0">
        <div class="flex items-center gap-3 transition-all duration-300">
            <div class="p-2 bg-white rounded-xl flex-shrink-0 shadow-sm flex items-center justify-center w-10 h-10">
                <svg class="w-6 h-6 text-[#4338CA]" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                </svg>
            </div>
            
            <div x-show="sidebarOpen" x-transition.opacity.duration.300ms class="transition-opacity">
                <h1 class="font-black text-white text-xl tracking-tight">SCFS LKBB</h1>
                <p class="text-xs text-indigo-200 uppercase tracking-widest font-bold">Panel Keuangan</p>
            </div>
        </div>
    </div>

    {{-- Navigasi Menu (BISA DI-SCROLL) --}}
    <nav class="flex-1 min-h-0 px-3 py-6 space-y-2 overflow-y-auto overflow-x-hidden [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-white/20 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-white/40">
        
        <div x-show="sidebarOpen" x-transition class="px-4 mb-2 mt-2 text-xs font-bold text-indigo-300 uppercase tracking-widest whitespace-nowrap">
            Menu Utama
        </div>

        <a href="{{ route('lkbb.dashboard') }}" wire:navigate
            class="flex items-center px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group whitespace-nowrap
            {{ request()->routeIs('lkbb.dashboard') 
                ? 'bg-white text-[#4338CA] shadow-lg' 
                : 'text-indigo-100 hover:bg-white/10 hover:text-white' }}"
            :class="sidebarOpen ? '' : 'justify-center'" 
            title="Dashboard">

            <svg class="w-6 h-6 flex-shrink-0 transition-colors
                {{ request()->routeIs('lkbb.dashboard') 
                    ? 'text-[#4338CA]' 
                    : 'text-indigo-300 group-hover:text-white' }}"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z M14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z M4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z M14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span x-show="sidebarOpen" x-transition class="ml-3 transition-opacity duration-300">Dashboard</span>
        </a>

        <div x-data="{ tokenOpen: true }" class="mt-1">
            <button 
                @click="if(!sidebarOpen) sidebarOpen = true; tokenOpen = !tokenOpen"
                class="w-full flex items-center justify-between px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group
                {{ $isTokenActive ? 'bg-white/20 text-white' : 'text-indigo-100 hover:bg-white/10 hover:text-white' }}"
                :class="sidebarOpen ? '' : 'justify-center'" title="Manajemen Token">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isTokenActive ? 'text-white' : 'text-indigo-300 group-hover:text-white' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0h-3m-9-4h18c1.1 0 2 .9 2 2v6c0 1.1-.9 2-2 2H3c-1.1 0-2-.9-2-2V9c0-1.1.9-2 2-2z" />
                    </svg>
                    <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Manajemen Token</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': tokenOpen}" class="w-4 h-4 transition-transform duration-300 {{ $isTokenActive ? 'text-white' : 'text-indigo-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="tokenOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 space-y-1 px-2 border-l-2 border-white/20 ml-4">
                <a href="{{ route('lkbb.injeksi-saldo') }}" wire:navigate class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('lkbb.injeksi-saldo') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Injeksi Saldo Baru
                </a>
                <a href="{{ route('lkbb.riwayat-injeksi') }}" wire:navigate class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('lkbb.riwayat-injeksi') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Riwayat Injeksi
                </a>
            </div>
        </div>

        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-xs font-bold text-indigo-300 uppercase tracking-widest whitespace-nowrap border-t border-white/10 pt-4">
            Verifikasi & Approval
        </div>

        <div x-data="{ approvalOpen: true }" class="mt-1">
            <button 
                @click="if(!sidebarOpen) sidebarOpen = true; approvalOpen = !approvalOpen"
                class="w-full flex items-center justify-between px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group
                {{ $isApprovalActive ? 'bg-white/20 text-white' : 'text-indigo-100 hover:bg-white/10 hover:text-white' }}"
                :class="sidebarOpen ? '' : 'justify-center'" title="Verifikasi & Approval">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isApprovalActive ? 'text-white' : 'text-indigo-300 group-hover:text-white' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Persetujuan</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': approvalOpen}" class="w-4 h-4 transition-transform duration-300 {{ $isApprovalActive ? 'text-white' : 'text-indigo-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <div x-show="approvalOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 space-y-1 px-2 border-l-2 border-white/20 ml-4">
                <a href="{{ route('approval.mahasiswa') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('approval.mahasiswa') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Mahasiswa
                </a>
                <a href="{{ route('approval.merchant') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('approval.merchant') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Merchant
                </a>
                <a href="{{ route('approval.pemasok') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('approval.pemasok') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Pemasok
                </a>
            </div>
        </div>

        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-xs font-bold text-indigo-300 uppercase tracking-widest whitespace-nowrap border-t border-white/10 pt-4">
            Data Saldo
        </div>

        <div x-data="{ masterDompetOpen: true }" class="mt-1">
            <button 
                @click="if(!sidebarOpen) sidebarOpen = true; masterDompetOpen = !masterDompetOpen"
                class="w-full flex items-center justify-between px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group
                {{ $isMasterDompetActive ? 'bg-white/20 text-white' : 'text-indigo-100 hover:bg-white/10 hover:text-white' }}"
                :class="sidebarOpen ? '' : 'justify-center'" title="Master Dompet">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isMasterDompetActive ? 'text-white' : 'text-indigo-300 group-hover:text-white' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Master Dompet</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': masterDompetOpen}" class="w-4 h-4 transition-transform duration-300 {{ $isMasterDompetActive ? 'text-white' : 'text-indigo-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <div x-show="masterDompetOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 space-y-1 px-2 border-l-2 border-white/20 ml-4">
                <a href="{{ route('saldo.bantuan') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('saldo.bantuan') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Dompet Mahasiswa
                </a>
                <a href="{{ route('keuangan.merchant') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('keuangan.merchant') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Dompet Merchant
                </a>
                <a href="{{ route('keuangan.pemasok') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('keuangan.pemasok') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Dompet Pemasok
                </a>
            </div>
        </div>

        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-xs font-bold text-indigo-300 uppercase tracking-widest whitespace-nowrap border-t border-white/10 pt-4">
            Operasional
        </div>

        <div x-data="{ operasionalOpen: true }" class="mt-1">
            <button @click="if(!sidebarOpen) sidebarOpen = true; operasionalOpen = !operasionalOpen" 
                    class="w-full flex items-center justify-between px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group {{ $isOperasionalActive ? 'bg-white/20 text-white' : 'text-indigo-100 hover:bg-white/10 hover:text-white' }}"
                    :class="sidebarOpen ? '' : 'justify-center'" title="Rantai Pasok">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isOperasionalActive ? 'text-white' : 'text-indigo-300 group-hover:text-white' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Rantai Pasok</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': operasionalOpen}" class="w-4 h-4 transition-transform duration-300 {{ $isOperasionalActive ? 'text-white' : 'text-indigo-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>

            <div x-show="operasionalOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 space-y-1 px-2 border-l-2 border-white/20 ml-4">
                <a href="{{ route('lkbb.scf.approval') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('lkbb.scf.approval') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Approval PO Pemasok
                </a>
                <a href="{{ route('supply-chain.create') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('supply-chain.create') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Pengajuan Pembiayaan
                </a>
                <a href="{{ route('supply-chain.bills') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('supply-chain.bills') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Tagihan Merchant
                </a>
            </div>
        </div>

        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-xs font-bold text-indigo-300 uppercase tracking-widest whitespace-nowrap border-t border-white/10 pt-4">
            Kas & Penagihan
        </div>

        <div x-data="{ setoranOpen: true }" class="mt-1">
            <button @click="if(!sidebarOpen) sidebarOpen = true; setoranOpen = !setoranOpen" 
                    class="w-full flex items-center justify-between px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group {{ $isSetoranActive ? 'bg-white/20 text-white' : 'text-indigo-100 hover:bg-white/10 hover:text-white' }}"
                    :class="sidebarOpen ? '' : 'justify-center'" title="Setoran Merchant">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isSetoranActive ? 'text-white' : 'text-indigo-300 group-hover:text-white' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                    </svg>
                    <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Setoran Merchant</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': setoranOpen}" class="w-4 h-4 transition-transform duration-300 {{ $isSetoranActive ? 'text-white' : 'text-indigo-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="setoranOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 space-y-1 px-2 border-l-2 border-white/20 ml-4">
                <a href="{{ route('keuangan.penagihan') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('keuangan.penagihan') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Penagihan Tunai
                </a>
                <a href="{{ route('keuangan.riwayat-fee') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('keuangan.riwayat-fee') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Riwayat & Audit Fee
                </a>
            </div>
        </div>

        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-xs font-bold text-indigo-300 uppercase tracking-widest whitespace-nowrap border-t border-white/10 pt-4">
            Arus Kas Digital
        </div>

        <div x-data="{ keuanganOpen: true }" class="mt-1">
            <button @click="if(!sidebarOpen) sidebarOpen = true; keuanganOpen = !keuanganOpen" 
                    class="w-full flex items-center justify-between px-3 py-3 text-[15px] font-bold rounded-xl transition-all duration-200 group {{ $isKeuanganActive ? 'bg-white/20 text-white' : 'text-indigo-100 hover:bg-white/10 hover:text-white' }}"
                    :class="sidebarOpen ? '' : 'justify-center'" title="Withdraw & Settlement">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isKeuanganActive ? 'text-white' : 'text-indigo-300 group-hover:text-white' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Withdraw & Settlement</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': keuanganOpen}" class="w-4 h-4 transition-transform duration-300 {{ $isKeuanganActive ? 'text-white' : 'text-indigo-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>

            <div x-show="keuanganOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 space-y-1 px-2 border-l-2 border-white/20 ml-4">
                <a href="{{ route('lkbb.wallets') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('lkbb.wallets') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Saldo & Wallet Utama
                </a>
                <a href="{{ route('lkbb.withdraw.merchant.approval') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('lkbb.withdraw.merchant.approval') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Approval WD Merchant
                </a>
                <a href="{{ route('lkbb.withdraw.pemasok.approval') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('lkbb.withdraw.pemasok.approval') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Approval WD Pemasok
                </a>
                <a href="{{ route('keuangan.pencairan') }}" class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all {{ request()->routeIs('keuangan.pencairan') ? 'text-[#4338CA] bg-white font-extrabold border-l-4 border-yellow-400 -ml-[2px]' : 'text-indigo-100 hover:text-white hover:bg-white/10 font-semibold' }}">
                    Log Pencairan Selesai
                </a>
            </div>
        </div>

        <div class="h-6"></div>
    </nav>

    {{-- User Profile & Logout (PASTI MUNCUL) --}}
    <div class="p-4 border-t border-white/10 bg-black/20 shrink-0">
        <div class="flex items-center gap-3 mb-4 px-1" :class="sidebarOpen ? '' : 'justify-center'">
            <div class="h-10 w-10 flex-shrink-0 rounded-full bg-white flex items-center justify-center text-[#4338CA] font-extrabold text-base shadow-md border-2 border-transparent relative">
                {{ substr(Auth::user()->name ?? 'L', 0, 2) }}
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-400 rounded-full border-2 border-[#4338CA]"></span>
            </div>
            
            <div x-show="sidebarOpen" x-transition class="overflow-hidden">
                <p class="text-[15px] font-extrabold text-white truncate">{{ Auth::user()->name ?? 'Admin LKBB' }}</p>
                <p class="text-xs text-indigo-200 font-medium truncate w-32">{{ Auth::user()->email ?? 'lkbb@scfs.com' }}</p>
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