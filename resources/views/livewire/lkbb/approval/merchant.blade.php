<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\MerchantProfile;

new #[Layout('layouts.lkbb')] class extends Component {
    use WithPagination;

    public $selectedMerchant = null;
    public bool $showModal = false;
    
    // Form States
    public string $catatan_penolakan = ''; 
    public $persentase_bagi_hasil = 10; // Default 10%

    #[Computed]
    public function pendingMerchants()
    {
        return MerchantProfile::with('user')
            ->where('status_verifikasi', 'menunggu_review')
            ->latest()
            ->paginate(10);
    }

    public function openModal($id)
    {
        $this->selectedMerchant = MerchantProfile::with('user')->findOrFail($id);
        $this->catatan_penolakan = ''; 
        $this->persentase_bagi_hasil = 10; // Reset ke default
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedMerchant = null;
        $this->resetValidation();
    }

    /**
     * Mengeksekusi Persetujuan (Approve) dengan validasi & keamanan IDOR
     */
    public function approveMerchant()
    {
        // 1. Validasi input Persentase Bagi Hasil
        $this->validate([
            'persentase_bagi_hasil' => 'required|numeric|min:0|max:100'
        ]);

        // 2. Security Check: Pastikan data ada di state dan statusnya memang 'menunggu_review'
        if (!$this->selectedMerchant || $this->selectedMerchant->status_verifikasi !== 'menunggu_review') {
            session()->flash('error', 'Aksi tidak valid atau status merchant sudah berubah.');
            return $this->closeModal();
        }

        // 3. Update Database
        $this->selectedMerchant->update([
            'status_verifikasi'     => 'disetujui',
            'persentase_bagi_hasil' => $this->persentase_bagi_hasil,
            'status_toko'           => 'tutup', // Biarkan merchant yang buka sendiri tokonya nanti
        ]);
        
        session()->flash('message', "Merchant {$this->selectedMerchant->nama_kantin} berhasil disetujui dengan bagi hasil {$this->persentase_bagi_hasil}%!");
        $this->closeModal();
    }

    /**
     * Mengeksekusi Penolakan (Reject) dengan validasi & keamanan IDOR
     */
    public function rejectMerchant()
    {
        $this->validate([
            'catatan_penolakan' => 'required|string|min:5' // Wajib diisi agar merchant tahu salahnya
        ]);

        // Security Check
        if (!$this->selectedMerchant || $this->selectedMerchant->status_verifikasi !== 'menunggu_review') {
            session()->flash('error', 'Aksi tidak valid atau status merchant sudah berubah.');
            return $this->closeModal();
        }

        $this->selectedMerchant->update([
            'status_verifikasi' => 'ditolak',
            'catatan_penolakan' => $this->catatan_penolakan
        ]);
        
        session()->flash('error', "Pengajuan Merchant {$this->selectedMerchant->nama_kantin} telah ditolak.");
        $this->closeModal();
    }
}; ?>

<div class="p-6 max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Approval Merchant Baru</h1>
            <p class="text-gray-500 text-sm mt-1">Verifikasi data toko, kelengkapan dokumen, dan atur persentase bagi hasil.</p>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl relative mb-4 shadow-sm flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl relative mb-4 shadow-sm flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[10px] text-gray-400 border-b border-gray-100 uppercase tracking-wider bg-gray-50/50">
                        <th class="py-4 px-6 font-bold">Data Kantin / Usaha</th>
                        <th class="py-4 px-6 font-bold">Data Pemilik</th>
                        <th class="py-4 px-6 font-bold">Lokasi</th>
                        <th class="py-4 px-6 font-bold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-600 divide-y divide-gray-50">
                    @forelse($this->pendingMerchants as $profile)
                    <tr class="hover:bg-gray-50 transition group">
                        <td class="py-4 px-6">
                            <div class="font-bold text-gray-900 text-sm">{{ $profile->nama_kantin }}</div>
                            <div class="text-[11px] text-gray-500 mt-1">
                                No HP: <span class="font-medium text-gray-700">{{ $profile->no_hp ?? '-' }}</span>
                            </div>
                        </td>

                        <td class="py-4 px-6">
                            <div class="text-gray-900 font-medium text-sm">{{ $profile->nama_pemilik }}</div>
                            <div class="text-[11px] text-gray-400 mt-0.5">NIK: {{ $profile->nik ?? '-' }}</div>
                        </td>

                        <td class="py-4 px-6">
                            <div class="text-gray-800 text-sm">{{ $profile->lokasi_blok ?? '-' }}</div>
                        </td>

                        <td class="py-4 px-6 text-right">
                            <button 
                                wire:click="openModal({{ $profile->id }})"
                                class="inline-flex items-center px-4 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-xs font-bold hover:bg-blue-100 transition shadow-sm">
                                Review Dokumen
                                <svg class="w-3.5 h-3.5 ml-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-16">
                            <div class="text-5xl mb-3 opacity-50">🎉</div>
                            <div class="text-gray-900 font-bold text-lg">Tidak ada antrean approval</div>
                            <div class="text-sm text-gray-500 mt-1">Semua pendaftaran kantin baru sudah diproses.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->pendingMerchants->hasPages())
            <div class="mt-4 pt-4 border-t border-gray-100">
                {{ $this->pendingMerchants->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Review --}}
    @if($showModal && $selectedMerchant)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-auto flex flex-col max-h-[90vh] overflow-hidden">
                
                {{-- Modal Header --}}
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Verifikasi Dokumen Mitra Kantin</h3>
                        <p class="text-[11px] text-gray-500 uppercase tracking-wider font-bold mt-1">ID Pengajuan: SC-MERCH-{{ str_pad($selectedMerchant->id, 4, '0', STR_PAD_LEFT) }}</p>
                    </div>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 transition p-1.5 rounded-lg hover:bg-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="px-6 py-4 overflow-y-auto flex-1 bg-white">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        {{-- Kolom Kiri: Data Teks --}}
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                                    <span class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Nama Kantin</span>
                                    <span class="block text-sm font-bold text-gray-900">{{ $selectedMerchant->nama_kantin }}</span>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                                    <span class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Nama Pemilik</span>
                                    <span class="block text-sm font-bold text-gray-900">{{ $selectedMerchant->nama_pemilik }}</span>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                                    <span class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">NIK</span>
                                    <span class="block text-sm font-bold text-gray-900">{{ $selectedMerchant->nik ?? '-' }}</span>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                                    <span class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">No WhatsApp</span>
                                    <span class="block text-sm font-bold text-gray-900">{{ $selectedMerchant->no_hp ?? '-' }}</span>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                                    <span class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Lokasi Blok</span>
                                    <span class="block text-sm font-bold text-gray-900">{{ $selectedMerchant->lokasi_blok ?? '-' }}</span>
                                </div>
                               <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                                    <span class="block text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1">
                                        Info Rekening
                                    </span>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-blue-900">
                                            {{ $selectedMerchant->nama_bank ?? '-' }}
                                        </span>
                                        <span class="text-[11px] text-gray-500 font-mono mt-0.5">
                                            {{ $selectedMerchant->no_rekening ?? '-' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <hr class="border-gray-100">

                            {{-- Form Setujui & Tolak --}}
                            <div class="grid grid-cols-1 gap-4">
                                <div class="bg-green-50 p-4 rounded-xl border border-green-100">
                                    <label class="block text-[10px] font-bold text-green-700 uppercase tracking-wider mb-2">Tentukan Persentase Bagi Hasil (%)</label>
                                    <input wire:model="persentase_bagi_hasil" type="number" min="0" max="100" class="w-full text-sm rounded-lg border-green-200 focus:border-green-500 focus:ring-green-500 bg-white">
                                    @error('persentase_bagi_hasil') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                    <p class="text-[10px] text-green-600 mt-1.5">*Angka ini menentukan potongan transaksi yang akan menjadi hak LKBB.</p>
                                </div>

                                <div class="bg-red-50 p-4 rounded-xl border border-red-100">
                                    <label class="block text-[10px] font-bold text-red-700 uppercase tracking-wider mb-2">Alasan Penolakan (Jika Ingin Ditolak)</label>
                                    <textarea wire:model="catatan_penolakan" class="w-full text-sm rounded-lg border-red-200 focus:border-red-500 focus:ring-red-500 bg-white" rows="2" placeholder="Cth: Foto KTP terpotong/buram..."></textarea>
                                    @error('catatan_penolakan') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Kolom Kanan: Foto Viewer --}}
                        <div class="space-y-4">
                            <div class="border rounded-xl p-3 bg-gray-50">
                                <span class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2 text-center">Dokumen KTP</span>
                                @if($selectedMerchant->foto_ktp)
                                    <a href="{{ asset('storage/' . $selectedMerchant->foto_ktp) }}" target="_blank" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:opacity-90 transition">
                                        <img src="{{ asset('storage/' . $selectedMerchant->foto_ktp) }}" class="w-full h-40 object-cover object-center">
                                    </a>
                                    <p class="text-[10px] text-center text-gray-400 mt-2">Klik gambar untuk memperbesar</p>
                                @else
                                    <div class="w-full h-40 bg-white border border-gray-200 border-dashed rounded-lg flex flex-col items-center justify-center text-gray-400">
                                        <svg class="w-8 h-8 mb-1 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        <span class="text-xs">File KTP tidak ditemukan</span>
                                    </div>
                                @endif
                            </div>

                            <div class="border rounded-xl p-3 bg-gray-50">
                                <span class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2 text-center">Foto Depan Kantin</span>
                                @if($selectedMerchant->foto_kantin)
                                    <a href="{{ asset('storage/' . $selectedMerchant->foto_kantin) }}" target="_blank" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:opacity-90 transition">
                                        <img src="{{ asset('storage/' . $selectedMerchant->foto_kantin) }}" class="w-full h-40 object-cover object-center">
                                    </a>
                                    <p class="text-[10px] text-center text-gray-400 mt-2">Klik gambar untuk memperbesar</p>
                                @else
                                    <div class="w-full h-40 bg-white border border-gray-200 border-dashed rounded-lg flex flex-col items-center justify-center text-gray-400">
                                        <svg class="w-8 h-8 mb-1 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        <span class="text-xs">File Foto tidak ditemukan</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center bg-gray-50">
                    <button wire:click="closeModal" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-100 transition focus:ring-4 focus:ring-gray-100">
                        Kembali
                    </button>
                    <div class="flex gap-3">
                        <button 
                            wire:click="rejectMerchant"
                            wire:confirm="Yakin ingin MENOLAK pengajuan kantin ini? Pastikan Anda sudah mengisi alasan penolakan."
                            class="px-5 py-2.5 text-sm font-bold text-red-600 bg-white border border-red-200 hover:bg-red-50 rounded-xl transition focus:ring-4 focus:ring-red-100">
                            Tolak Pengajuan
                        </button>
                        <button 
                            wire:click="approveMerchant"
                            wire:confirm="Setujui pengajuan ini dengan persentase bagi hasil {{ $persentase_bagi_hasil }}%?"
                            class="px-6 py-2.5 text-sm font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl shadow-sm transition focus:ring-4 focus:ring-green-100 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Setujui & Aktifkan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>