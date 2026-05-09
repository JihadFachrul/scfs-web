<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Withdrawal;
use App\Models\SetoranTunai;

new 
#[Layout('layouts.lkbb')] 
class extends Component {
    
    // 1. Ambil data Fee dari potongan otomatis (Hanya yang disetujui & ada potongannya)
    #[Computed]
    public function feeOtomatis()
    {
        return Withdrawal::with(['merchantProfile', 'merchant'])
                ->where('status', 'disetujui')
                ->where('potongan_lkbb', '>', 0)
                ->latest()
                ->get();
    }

    // 2. Ambil data Setoran Tunai manual dari petugas
    #[Computed]
    public function setoranManual()
    {
        return SetoranTunai::with('merchant')
                ->latest()
                ->get();
    }

    // 3. Kalkulasi Ringkasan (Summary)
    #[Computed]
    public function totalFeeOtomatis()
    {
        return $this->feeOtomatis->sum('potongan_lkbb');
    }

    #[Computed]
    public function totalSetoranManual()
    {
        return $this->setoranManual->sum('nominal');
    }

    #[Computed]
    public function grandTotal()
    {
        return $this->totalFeeOtomatis + $this->totalSetoranManual;
    }
}; ?>

<div class="py-8 px-6 md:px-8 w-full max-w-7xl mx-auto space-y-6" x-data="{ tab: 'otomatis' }">
    
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Riwayat Fee & Setoran Tunai</h2>
        <p class="text-gray-500 text-sm mt-1">Pantau seluruh pendapatan LKBB dari potongan pencairan dan setoran fisik merchant.</p>
    </div>

    {{-- KARTU RINGKASAN PENDAPATAN --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Potongan WD (Otomatis)</p>
            <h3 class="text-2xl font-extrabold text-gray-900">Rp {{ number_format($this->totalFeeOtomatis, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Setoran Fisik (Manual)</p>
            <h3 class="text-2xl font-extrabold text-gray-900">Rp {{ number_format($this->totalSetoranManual, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-blue-600 border border-blue-700 rounded-2xl p-5 shadow-lg shadow-blue-200 text-white">
            <p class="text-[10px] font-bold text-blue-200 uppercase tracking-wider mb-1">Grand Total Pendapatan LKBB</p>
            <h3 class="text-2xl font-extrabold">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</h3>
        </div>
    </div>

    {{-- NAVIGASI TAB --}}
    <div class="flex space-x-2 border-b border-gray-200 mb-6">
        <button @click="tab = 'otomatis'" 
                :class="tab === 'otomatis' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 border-b-2 hover:border-gray-300'"
                class="px-4 py-3 text-sm font-bold border-b-2 transition-colors duration-200 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
            Via Potongan WD
        </button>
        <button @click="tab = 'manual'" 
                :class="tab === 'manual' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 border-b-2 hover:border-gray-300'"
                class="px-4 py-3 text-sm font-bold border-b-2 transition-colors duration-200 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" /></svg>
            Via Setoran Tunai
        </button>
    </div>

    {{-- TAB 1: POTONGAN OTOMATIS (DARI WITHDRAWAL) --}}
    <div x-show="tab === 'otomatis'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="font-bold text-gray-900 text-sm">Riwayat Potongan Auto-Deduct</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-white text-gray-400 text-[10px] uppercase font-bold tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4">Waktu / No. Transaksi</th>
                            <th class="px-6 py-4">Kantin (Merchant)</th>
                            <th class="px-6 py-4 text-right">Penarikan Bersih</th>
                            <th class="px-6 py-4 text-right text-emerald-600">Pendapatan Fee LKBB</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->feeOtomatis as $wd)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-mono text-xs font-bold text-gray-900">{{ $wd->nomor_pencairan }}</div>
                                    <div class="text-[10px] text-gray-500 mt-0.5">{{ $wd->created_at->format('d M Y, H:i') }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-800">{{ $wd->merchant->name ?? 'User '.$wd->merchant_id }}</div>
                                    <div class="text-[10px] text-gray-400">ID: {{ $wd->merchant_id }}</div>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-600">
                                    Rp {{ number_format($wd->nominal_bersih, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="text-sm font-extrabold text-emerald-600">+ Rp {{ number_format($wd->potongan_lkbb, 0, ',', '.') }}</div>
                                    <div class="text-[9px] text-emerald-400 font-bold uppercase tracking-wider mt-1">Lunas (Auto)</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                                    <p class="text-sm font-bold">Belum ada pendapatan fee dari pemotongan otomatis.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TAB 2: SETORAN MANUAL (TUNAI FISIK) --}}
    <div x-show="tab === 'manual'" style="display: none;" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="font-bold text-gray-900 text-sm">Riwayat Setoran Uang Fisik</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-white text-gray-400 text-[10px] uppercase font-bold tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4">Waktu / No. Setoran</th>
                            <th class="px-6 py-4">Kantin (Merchant)</th>
                            <th class="px-6 py-4">Nama Petugas Penagih</th>
                            <th class="px-6 py-4 text-right text-blue-600">Nominal Disetor</th>
                            <th class="px-6 py-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->setoranManual as $setoran)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-mono text-xs font-bold text-gray-900">{{ $setoran->nomor_setoran }}</div>
                                    <div class="text-[10px] text-gray-500 mt-0.5">{{ $setoran->created_at->format('d M Y, H:i') }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-800">{{ $setoran->merchant->name ?? 'User '.$setoran->merchant_id }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ $setoran->nama_petugas ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="text-sm font-extrabold text-blue-600">Rp {{ number_format($setoran->nominal, 0, ',', '.') }}</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if(strtolower($setoran->status) == 'lunas' || strtolower($setoran->status) == 'berhasil')
                                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] px-2.5 py-1 rounded-md font-bold uppercase tracking-wider">Berhasil</span>
                                    @else
                                        <span class="bg-amber-50 text-amber-700 border border-amber-200 text-[10px] px-2.5 py-1 rounded-md font-bold uppercase tracking-wider">{{ $setoran->status }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                    <p class="text-sm font-bold">Belum ada riwayat setoran tunai manual.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>