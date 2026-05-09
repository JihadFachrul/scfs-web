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
    $isApprovalActive = request()->routeIs('approval.*', 'supply-chain.approval');
    $isMasterDompetActive = request()->is('keuangan/mahasiswa*', 'keuangan/merchant*', 'keuangan/pemasok*') || request()->routeIs('saldo.bantuan');
    $isOperasionalActive = request()->routeIs('supply-chain.create', 'supply-chain.bills', 'lkbb.scf.approval');
    
    // PERBAIKAN: Logika dipisah untuk menu Setoran dan menu Withdraw
    $isSetoranActive = request()->routeIs('keuangan.penagihan', 'keuangan.riwayat-fee');
    $isKeuanganActive = request()->is('keuangan/pencairan*') || request()->routeIs('lkbb.wallets', 'lkbb.withdraw.merchant.approval', 'lkbb.withdraw.pemasok.approval');
@endphp

<aside 
    x-data="{ sidebarOpen: true }"
    :class="sidebarOpen ? 'w-72' : 'w-20'"
    class="bg-white border-r border-gray-200 h-screen sticky top-0 flex flex-col transition-all duration-300 ease-in-out z-40 hidden md:flex"
>
    
    <button 
        @click="sidebarOpen = !sidebarOpen"
        class="absolute -right-3 top-9 bg-white border border-gray-200 text-gray-500 rounded-full p-1 shadow-sm hover:text-blue-600 hover:border-blue-300 transition-colors z-50"
    >
        <svg x-show="sidebarOpen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        <svg x-show="!sidebarOpen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
    </button>

    <div class="h-20 flex items-center px-4 border-b border-gray-100 overflow-hidden whitespace-nowrap shrink-0">
        <div class="flex items-center gap-3 transition-all duration-300">
            <div class="p-2 bg-blue-600 rounded-lg flex-shrink-0 text-white flex items-center justify-center w-10 h-10">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                </svg>
            </div>
            
            <div x-show="sidebarOpen" x-transition.opacity.duration.300ms class="transition-opacity">
                <h1 class="font-bold text-gray-800 text-lg tracking-wide">SCFS LKBB</h1>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Panel Keuangan</p>
            </div>
        </div>
    </div>

    <nav class="flex-1 px-3 py-6 space-y-1.5 overflow-y-auto overflow-x-hidden [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-gray-300">
        
        <div x-show="sidebarOpen" x-transition class="px-4 mb-2 mt-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap">
            Menu Utama
        </div>

        <a href="{{ route('lkbb.dashboard') }}" wire:navigate
           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 group whitespace-nowrap
           {{ request()->routeIs('lkbb.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
           :class="sidebarOpen ? '' : 'justify-center'" title="Dashboard">
            <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('lkbb.dashboard') ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span x-show="sidebarOpen" x-transition class="ml-3 transition-opacity duration-300">Dashboard</span>
        </a>

        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-[10px] font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap">
            Verifikasi & Approval
        </div>

        <div x-data="{ approvalOpen: true }" class="mt-1">
            <button @click="if(!sidebarOpen) sidebarOpen = true; approvalOpen = !approvalOpen" 
                    class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 group {{ $isApprovalActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                    :class="sidebarOpen ? '' : 'justify-center'" title="Verifikasi & Approval">
                <div class="flex items-center">
                    <svg class="w-5 h-5 flex-shrink-0 {{ $isApprovalActive ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span x-show="sidebarOpen" class="ml-3 font-semibold whitespace-nowrap">Persetujuan</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': approvalOpen}" class="w-4 h-4 {{ $isApprovalActive ? 'text-blue-600' : 'text-gray-400' }} transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>

            <div x-show="approvalOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-1 space-y-1 px-2">
                <a href="{{ route('approval.mahasiswa') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('approval.mahasiswa') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Mahasiswa
                </a>
                <a href="{{ route('approval.merchant') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('approval.merchant') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Merchant
                </a>
                <a href="{{ route('approval.pemasok') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('approval.pemasok') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Pemasok
                </a>
            </div>
        </div>

        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-[10px] font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap">
            Data Saldo
        </div>

        <div x-data="{ masterDompetOpen: true }" class="mt-1">
            <button @click="if(!sidebarOpen) sidebarOpen = true; masterDompetOpen = !masterDompetOpen" 
                    class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 group {{ $isMasterDompetActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                    :class="sidebarOpen ? '' : 'justify-center'" title="Master Dompet">
                <div class="flex items-center">
                    <svg class="w-5 h-5 flex-shrink-0 {{ $isMasterDompetActive ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                    <span x-show="sidebarOpen" class="ml-3 font-semibold whitespace-nowrap">Master Dompet</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': masterDompetOpen}" class="w-4 h-4 {{ $isMasterDompetActive ? 'text-blue-600' : 'text-gray-400' }} transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>

            <div x-show="masterDompetOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-1 space-y-1 px-2">
                <a href="{{ route('saldo.bantuan') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('saldo.bantuan') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Dompet Mahasiswa
                </a>
                <a href="{{ route('keuangan.merchant') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('keuangan.merchant') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Dompet Merchant
                </a>
                <a href="{{ route('keuangan.pemasok') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('keuangan.pemasok') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Dompet Pemasok
                </a>
            </div>
        </div>

        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-[10px] font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap">
            Operasional
        </div>

        <div x-data="{ operasionalOpen: true }" class="mt-1">
            <button @click="if(!sidebarOpen) sidebarOpen = true; operasionalOpen = !operasionalOpen" 
                    class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 group {{ $isOperasionalActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                    :class="sidebarOpen ? '' : 'justify-center'" title="Rantai Pasok">
                <div class="flex items-center">
                    <svg class="w-5 h-5 flex-shrink-0 {{ $isOperasionalActive ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    <span x-show="sidebarOpen" class="ml-3 font-semibold whitespace-nowrap">Rantai Pasok</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': operasionalOpen}" class="w-4 h-4 {{ $isOperasionalActive ? 'text-blue-600' : 'text-gray-400' }} transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>

            <div x-show="operasionalOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-1 space-y-1 px-2">
                
                <a href="{{ route('lkbb.scf.approval') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('lkbb.scf.approval') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Approval PO Pemasok
                </a>

                <a href="{{ route('supply-chain.create') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('supply-chain.create') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Pengajuan Pembiayaan
                </a>
                <a href="{{ route('supply-chain.bills') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('supply-chain.bills') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Tagihan Merchant
                </a>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- MENU BARU: KAS & PENAGIHAN (WARNA AMBER)   --}}
        {{-- ========================================== --}}
        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-[10px] font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap">
            Kas & Penagihan
        </div>

        <div x-data="{ setoranOpen: true }" class="mt-1">
            <button @click="if(!sidebarOpen) sidebarOpen = true; setoranOpen = !setoranOpen" 
                    class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 group {{ $isSetoranActive ? 'bg-amber-50 text-amber-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                    :class="sidebarOpen ? '' : 'justify-center'" title="Setoran Merchant">
                <div class="flex items-center">
                    <svg class="w-5 h-5 flex-shrink-0 {{ $isSetoranActive ? 'text-amber-600' : 'text-gray-400 group-hover:text-gray-500' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                    </svg>
                    <span x-show="sidebarOpen" class="ml-3 font-semibold whitespace-nowrap">Setoran Merchant</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': setoranOpen}" class="w-4 h-4 {{ $isSetoranActive ? 'text-amber-600' : 'text-gray-400' }} transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="setoranOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-1 space-y-1 px-2">
                <a href="{{ route('keuangan.penagihan') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('keuangan.penagihan') ? 'text-amber-700 bg-amber-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Penagihan Tunai
                </a>
                <a href="{{ route('keuangan.riwayat-fee') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('keuangan.riwayat-fee') ? 'text-amber-700 bg-amber-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Riwayat & Audit Fee
                </a>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- ARUS KAS DIGITAL (BERFOKUS PADA WITHDRAW)  --}}
        {{-- ========================================== --}}
        <div x-show="sidebarOpen" x-transition class="px-4 mb-1 mt-6 text-[10px] font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap">
            Arus Kas Digital
        </div>

        <div x-data="{ keuanganOpen: true }" class="mt-1">
            <button @click="if(!sidebarOpen) sidebarOpen = true; keuanganOpen = !keuanganOpen" 
                    class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 group {{ $isKeuanganActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                    :class="sidebarOpen ? '' : 'justify-center'" title="Withdraw & Settlement">
                <div class="flex items-center">
                    <svg class="w-5 h-5 flex-shrink-0 {{ $isKeuanganActive ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span x-show="sidebarOpen" class="ml-3 font-semibold whitespace-nowrap">Withdraw & Settlement</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': keuanganOpen}" class="w-4 h-4 {{ $isKeuanganActive ? 'text-blue-600' : 'text-gray-400' }} transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>

            <div x-show="keuanganOpen && sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-1 space-y-1 px-2">
                <a href="{{ route('lkbb.wallets') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('lkbb.wallets') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Saldo & Wallet Utama
                </a>
                <a href="{{ route('lkbb.withdraw.merchant.approval') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('lkbb.withdraw.merchant.approval') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Approval WD Merchant
                </a>
                <a href="{{ route('lkbb.withdraw.pemasok.approval') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('lkbb.withdraw.pemasok.approval') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Approval WD Pemasok
                </a>
                <a href="{{ route('keuangan.pencairan') }}" class="flex items-center pl-10 pr-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('keuangan.pencairan') ? 'text-blue-700 bg-blue-50/50 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    Log Pencairan Selesai
                </a>
            </div>
        </div>

        <div class="h-4"></div>
    </nav>

    <div class="p-4 border-t border-gray-100 bg-gray-50/50 shrink-0 mt-auto">
        <div class="flex items-center gap-3 mb-4 px-1" :class="sidebarOpen ? '' : 'justify-center'">
            <div class="h-9 w-9 flex-shrink-0 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm shadow-sm border border-blue-200">
                {{ substr(Auth::user()->name ?? 'L', 0, 2) }}
            </div>
            
            <div x-show="sidebarOpen" x-transition class="overflow-hidden flex-1">
                <p class="text-sm font-bold text-gray-800 truncate">{{ Auth::user()->name ?? 'Admin LKBB' }}</p>
                <p class="text-[10px] text-gray-500 truncate w-32">{{ Auth::user()->email ?? 'lkbb@scfs.com' }}</p>
            </div>
        </div>

        <button wire:click="logout" 
                class="w-full flex items-center px-3 py-2 text-sm font-medium text-red-600 bg-white border border-red-100 rounded-lg hover:bg-red-50 hover:border-red-200 transition-all duration-200 shadow-sm"
                :class="sidebarOpen ? 'justify-center' : 'justify-center border-0 bg-transparent hover:bg-red-50 shadow-none'"
                title="Keluar">
            <svg class="w-5 h-5 flex-shrink-0" :class="sidebarOpen ? 'mr-2' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span x-show="sidebarOpen" x-transition>Keluar</span>
        </button>
    </div>

</aside>