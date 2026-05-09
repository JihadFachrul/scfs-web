<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\SupplyChain;
use App\Models\SupplyOrder; 
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

new #[Layout('layouts.lkbb')] class extends Component {
    
    // Properti untuk Modal Konfirmasi Reject
    public $showRejectModal = false;
    public $selectedId = null;
    public $rejectReason = '';

    // Properti untuk Modal Detail Pesanan 👈 TAMBAHKAN INI
    public $isDetailModalOpen = false;
    public $selectedPoDetails = [];
    public $selectedRequestInfo = null;

    // Mengambil daftar pengajuan yang HANYA menunggu persetujuan LKBB
    #[Computed]
    public function pendingRequests()
    {
        return SupplyChain::with([
                'merchant', // Asumsi ada relasi belongsTo ke User (Merchant)
                'supplier'  // Asumsi ada relasi belongsTo ke User (Pemasok)
            ])
            ->where('status', 'PENDING') 
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // Fungsi Buka Modal Detail 👈 TAMBAHKAN INI
    // Fungsi Buka Modal Detail
    public function openDetailModal($id)
    {
        // 1. Cari data pengajuan beserta relasi user-nya
        $request = SupplyChain::with(['merchant', 'supplier'])->find($id);
        
        if ($request) {
            $this->selectedRequestInfo = $request;
            
            // 2. Ambil pesanan dari SupplyOrder beserta relasi detail dan produknya
            $order = SupplyOrder::with(['details.produkPemasok'])
                ->where('id_pengajuan', $request->invoice_number)
                ->first();
            
            // 3. Masukkan data detail ke dalam variabel array
            $this->selectedPoDetails = $order ? $order->details : [];
            
            $this->isDetailModalOpen = true;
        }
    }

    // Fungsi Tutup Modal Detail 👈 TAMBAHKAN INI
    public function closeDetailModal()
    {
        $this->isDetailModalOpen = false;
        $this->selectedPoDetails = [];
        $this->selectedRequestInfo = null;
    }

    // Fungsi Buka Modal Reject (diperbarui agar rapi)
    public function openRejectModal($id)
    {
        $this->selectedId = $id;
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    // Fungsi Approve
    public function approve($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $request = SupplyChain::where('id', $id)
                    ->where('status', 'PENDING')
                    ->lockForUpdate()
                    ->firstOrFail();

                // 1. Update status di tabel LKBB menjadi FUNDED
                $request->update([
                    'status' => 'FUNDED',
                    'updated_at' => now(),
                ]);

                // 2. OTOMATIS UPDATE STATUS DI TABEL PEMASOK
                SupplyOrder::where('id_pengajuan', $request->invoice_number)
                    ->update([
                        'status_pembiayaan' => 'siap_diproduksi'
                    ]);
            });

            session()->flash('message', "Pengajuan PO berhasil disetujui. Pemasok kini dapat memulai produksi.");
        } catch (\Exception $e) {
            \Log::error("Approval PO Failed: " . $e->getMessage());
            session()->flash('error', 'Terjadi kesalahan saat menyetujui data.');
        }
    }

    // Fungsi Reject (Tolak)
    public function confirmReject()
    {
        $this->validate([
            'rejectReason' => 'required|string|min:5|max:255',
        ]);

        try {
            DB::transaction(function () {
                $request = SupplyChain::where('id', $this->selectedId)
                    ->where('status', 'PENDING')
                    ->lockForUpdate()
                    ->firstOrFail();

                // 1. Update status penolakan di tabel LKBB
                $request->update([
                    'status' => 'REJECTED', 
                    'updated_at' => now(),
                ]);

                // 2. KEMBALIKAN STATUS DI TABEL PEMASOK
                SupplyOrder::where('id_pengajuan', $request->invoice_number)
                    ->update([
                        'status_pembiayaan' => 'siap_diajukan', // Kembalikan ke awal
                        'id_pengajuan' => null // Putus jembatannya karena ditolak
                    ]);
            });

            $this->showRejectModal = false;
            session()->flash('message', 'Pengajuan berhasil ditolak.');
        } catch (\Exception $e) {
            \Log::error("Reject PO Failed: " . $e->getMessage());
            session()->flash('error', 'Gagal menolak pengajuan.');
        }
    }
}; ?>

<div class="p-6 max-w-7xl mx-auto">
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Approval Pembiayaan Rantai Pasok</h1>
            <p class="text-gray-500 text-sm mt-1">Daftar pengajuan PO dari Merchant yang membutuhkan persetujuan pendanaan LKBB.</p>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 shadow-sm">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 shadow-sm">
            {{ session('error') }}
        </div>
    @endif
    
   
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">ID PO</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Merchant (Kantin)</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Pemasok Tujuan</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Pembiayaan (Rp)</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Jatuh Tempo</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->pendingRequests as $req)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#PO-{{ str_pad($req->id, 5, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $req->merchant->name ?? 'Unknown' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $req->supplier->name ?? 'Unknown' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900">Rp {{ number_format($req->capital_amount, 0, ',', '.') }}</div>
                                <div class="text-xs text-green-600">+ Margin: Rp {{ number_format($req->margin_amount, 0, ',', '.') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                {{ \Carbon\Carbon::parse($req->due_date)->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="openDetailModal({{ $req->id }})" class="text-gray-700 bg-gray-100 border border-gray-300 hover:bg-gray-200 px-3 py-1.5 rounded-md shadow-sm transition-colors text-xs font-bold mr-2">
                                    Detail
                                </button>
                                <button wire:click="approve({{ $req->id }})" wire:confirm="Apakah Anda yakin ingin menyetujui pendanaan ini?" class="text-white bg-green-600 hover:bg-green-700 px-3 py-1.5 rounded-md shadow-sm transition-colors text-xs font-bold mr-2">
                                    Approve
                                </button>
                                <button wire:click="openRejectModal({{ $req->id }})" class="text-white bg-red-600 hover:bg-red-700 px-3 py-1.5 rounded-md shadow-sm transition-colors text-xs font-bold">
                                    Reject
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 text-sm">
                                Tidak ada pengajuan pembiayaan yang menunggu persetujuan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($isDetailModalOpen && $selectedRequestInfo)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-detail" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeDetailModal"></div>
            
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg leading-6 font-extrabold text-gray-900" id="modal-detail">
                            Detail Pesanan #PO-{{ str_pad($selectedRequestInfo->id, 5, '0', STR_PAD_LEFT) }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-1">Invoice Ref: {{ $selectedRequestInfo->invoice_number }}</p>
                    </div>
                    <button wire:click="closeDetailModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <div class="bg-white px-6 py-5">
                    <div class="grid grid-cols-2 gap-4 mb-6 bg-blue-50/50 p-4 rounded-lg border border-blue-100">
                        <div>
                            <span class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Pemesan (Merchant)</span>
                            <span class="block text-sm font-semibold text-gray-900 mt-1">{{ $selectedRequestInfo->merchant->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Tujuan (Pemasok)</span>
                            <span class="block text-sm font-semibold text-gray-900 mt-1">{{ $selectedRequestInfo->supplier->name ?? '-' }}</span>
                        </div>
                    </div>

                    <h4 class="text-sm font-bold text-gray-800 mb-3 border-b pb-2">Rincian Barang yang Dipesan:</h4>
                    
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-gray-500">Nama Bahan/Barang</th>
                                    <th class="px-4 py-2 text-center text-xs font-bold text-gray-500">Qty</th>
                                    <th class="px-4 py-2 text-right text-xs font-bold text-gray-500">Harga Satuan</th>
                                    <th class="px-4 py-2 text-right text-xs font-bold text-gray-500">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($selectedPoDetails as $detail)
                                    <tr class="hover:bg-gray-50 text-sm">
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            {{ $detail->nama_bahan_snapshot ?? $detail->produkPemasok->nama_bahan ?? 'Data Tidak Diketahui' }}
                                        </td>
                                        
                                        <td class="px-4 py-3 text-center text-gray-700">{{ $detail->qty ?? 0 }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700">Rp {{ number_format($detail->harga_satuan_snapshot ?? 0, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900">Rp {{ number_format($detail->subtotal ?? 0, 0, ',', '.') }}</td>
                                    </tr> 
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">Detail barang tidak ditemukan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Total Pembiayaan Pokok:</td>
                                    <td class="px-4 py-3 text-right font-extrabold text-blue-700 text-base">Rp {{ number_format($selectedRequestInfo->capital_amount, 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 sm:flex sm:flex-row-reverse">
                    <button wire:click="closeDetailModal" type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-bold text-gray-700 hover:bg-gray-100 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        Tutup Panel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($showRejectModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Tolak Pengajuan PO #{{ $selectedId }}</h3>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Alasan Penolakan</label>
                            <textarea wire:model="rejectReason" rows="3" class="w-full border-gray-300 rounded-md focus:ring-red-500 focus:border-red-500" placeholder="Contoh: Dana LKBB sedang difokuskan untuk hal lain..."></textarea>
                            @error('rejectReason') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button wire:click="confirmReject" type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Konfirmasi Tolak
                        </button>
                        <button wire:click="$set('showRejectModal', false)" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>