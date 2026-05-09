<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Models\SetoranTunai;
use App\Models\MerchantProfile;

new #[Layout('layouts.lkbb')] class extends Component {
    use WithPagination;

    public $search = '';
    public $tab = 'pending'; // 'pending' (Belum Lunas), 'success' (Lunas)

    // Form Simulasi (Untuk Testing)
    public $showSimulasiModal = false;
    public $simulasiUserId = '';
    public $simulasiAmount = '';

    // Modal Konfirmasi Pelunasan
    public $showConfirmModal = false;
    public $selectedTransaction = null;

    public function updatingSearch() {
        $this->resetPage();
    }

    public function updatingTab() {
        $this->resetPage();
    }

    #[Computed]
    public function riwayatHariIni()
    {
        // Mengambil transaksi dengan tipe 'tagihan_merchant'
        $tagihan = Transaction::with('user')
            ->where('type', 'tagihan_merchant')
            ->where('status', $this->tab)
            ->when($this->search, function($query) {
                $query->whereHas('user', function($q) {
                    $q->where('name', 'like', '%'.$this->search.'%');
                })->orWhere('order_id', 'like', '%'.$this->search.'%');
            })
            ->latest()
            ->paginate(10);

        return [
            'daftarTagihan' => $tagihan,
            // Mengambil user merchant untuk dropdown simulasi
            'merchants' => User::whereIn('role', ['merchant', 'Merchant'])->get()
        ];
    }

    public function confirmPayment($transactionId) {
        $this->selectedTransaction = Transaction::with('user')->find($transactionId);
        $this->showConfirmModal = true;
    }

    // --- FUNGSI INTI: TERIMA SETORAN TUNAI ---
    public function terimaSetoran($transactionId)
    {
        $trx = Transaction::with('user')->find($transactionId);
        if (!$trx || $trx->status !== 'pending') return;

        try {
            DB::transaction(function () use ($setId, $petugas) {
                
                $setoran = SetoranTunai::where('id', $setId)
                                ->lockForUpdate()
                                ->firstOrFail();

                $merchant = MerchantProfile::where('user_id', $setoran->merchant_id)
                                ->lockForUpdate()
                                ->firstOrFail();

                $setoran->update([
                    'status' => 'selesai',
                    'nama_petugas' => $petugas
                ]);

                $hutangLama = (float) $merchant->tagihan_setoran_tunai;
                $nominalSetor = (float) $setoran->nominal;
                
                $hutangBaru = max(0, $hutangLama - $nominalSetor);
                $merchant->update(['tagihan_setoran_tunai' => $hutangBaru]);
            });

            $this->isModalOpen = false;
            $this->selectedSetoranId = null;
            $this->nama_petugas = '';
            
            unset($this->tiketPending); 
            unset($this->riwayatHariIni); 

            session()->flash('success', 'Uang tunai berhasil diterima dan hutang kantin telah lunas!');

        } catch (\Exception $e) {
            session()->flash('error', 'Gagal memproses: ' . $e->getMessage());
        }

        $this->showConfirmModal = false;
        $this->selectedTransaction = null;
    }

    // --- FUNGSI TESTING: Membuat Tagihan Dummy ---
    public function submitSimulasi()
    {
        $this->validate([
            'simulasiUserId' => 'required',
            'simulasiAmount' => 'required|numeric|min:5000',
        ]);

        Transaction::create([
            'user_id' => $this->simulasiUserId,
            'order_id' => 'TGH-' . Str::upper(Str::random(8)),
            'total_amount' => $this->simulasiAmount,
            'type' => 'tagihan_merchant',
            'status' => 'pending',
            'description' => 'Tagihan setoran tunai dari transaksi Mahasiswa',
        ]);

        $this->showSimulasiModal = false;
        $this->reset(['simulasiUserId', 'simulasiAmount']);
        session()->flash('message', 'Simulasi Tagihan Merchant berhasil dibuat!');
    }
}; ?>

<div class="p-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Penagihan Merchant (Collection)</h1>
            <p class="text-gray-500 text-sm mt-1">Kelola tagihan Merchant atas penerimaan uang tunai dari Mahasiswa.</p>
        </div>
        <div class="flex gap-3 w-full md:w-auto">
            <div class="relative flex-1 md:w-64">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari Merchant/ID..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm">
                <div class="absolute left-3 top-2.5 text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>
            <button wire:click="$set('showSimulasiModal', true)" class="px-4 py-2 bg-orange-50 text-orange-600 border border-orange-200 rounded-lg hover:bg-orange-600 hover:text-white text-sm font-bold whitespace-nowrap transition">
                + Simulasi Tagihan
            </button>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl mb-4">
            <span class="font-bold">{{ session('error') }}</span>
        </div>
    @endif

    <div class="flex gap-4 border-b border-gray-200 mb-6">
        <button wire:click="$set('tab', 'pending')" class="pb-3 px-1 border-b-2 font-semibold text-sm transition-colors {{ $tab === 'pending' ? 'border-orange-600 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Menunggu Setoran (Hutang)
        </button>
        <button wire:click="$set('tab', 'success')" class="pb-3 px-1 border-b-2 font-semibold text-sm transition-colors {{ $tab === 'success' ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Sudah Lunas
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500 font-semibold">
                        <th class="px-6 py-4">ID Tagihan & Waktu</th>
                        <th class="px-6 py-4">Nama Merchant</th>
                        <th class="px-6 py-4">Nominal Disetorkan</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($daftarTagihan as $trx)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800">{{ $trx->order_id }}</div>
                                <div class="text-xs text-gray-400 mt-1">{{ $trx->created_at->format('d M Y, H:i') }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-blue-600">{{ $trx->user->name ?? 'User Terhapus' }}</div>
                            </td>
                            <td class="px-6 py-4 font-extrabold text-gray-900">
                                Rp {{ number_format($trx->total_amount, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4">
                                @if($trx->status === 'pending')
                                    <span class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-xs font-bold">Belum Disetor</span>
                                @else
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold">Lunas</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($trx->status === 'pending')
                                    <button 
                                        wire:click="confirmPayment({{ $trx->id }})" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition shadow-sm active:scale-95">
                                        Terima Uang & Lunaskan
                                    </button>
                                @else
                                    <span class="text-xs text-gray-400 italic font-medium bg-gray-50 px-3 py-1 rounded-full border border-gray-100">
                                        Transaksi Selesai
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <li class="p-8 text-center text-sm text-gray-500 font-medium">Belum ada uang fisik yang diterima hari ini.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" x-data x-transition>
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-7 relative">
                <div class="flex justify-center mb-5">
                    <div class="p-3.5 bg-blue-50 text-blue-600 rounded-full">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" /></svg>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-center text-gray-900 mb-1">Konfirmasi Setoran</h3>
                <p class="text-sm text-center text-gray-500 mb-6 leading-relaxed">Siapa nama petugas LKBB yang membawa uang fisik ini ke kantor?</p>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nominal Tunai (Rp)</label>
                    <input type="number" wire:model="simulasiAmount" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 px-4 py-2 text-sm" placeholder="Contoh: 150000">
                    @error('simulasiAmount') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="$set('showSimulasiModal', false)" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 text-sm font-semibold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm font-semibold">Buat Tagihan</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if($showConfirmModal && $selectedTransaction)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl p-8 w-full max-w-sm shadow-2xl text-center border border-gray-100">
            <div class="w-20 h-20 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>

            <h3 class="text-xl font-extrabold text-gray-900 mb-2">Konfirmasi Terima Kas</h3>
            <p class="text-sm text-gray-500 mb-6 leading-relaxed">
                Pastikan Anda telah menerima uang tunai fisik sebesar <br>
                <span class="font-bold text-gray-900 text-xl block mt-1">Rp {{ number_format($selectedTransaction->total_amount, 0, ',', '.') }}</span>
                dari <span class="font-bold text-blue-600">{{ $selectedTransaction->user->name }}</span>
            </p>

            <div class="space-y-3">
                <button 
                    wire:click="terimaSetoran({{ $selectedTransaction->id }})" 
                    wire:loading.attr="disabled"
                    class="w-full py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-bold transition-all disabled:opacity-50 flex items-center justify-center">
                    <span wire:loading.remove wire:target="terimaSetoran">
                        Ya, Sudah Saya Terima
                    </span>

                    <div wire:loading wire:target="terimaSetoran">
                        <div class="flex flex-row items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-white">Memproses...</span>
                        </div>
                    </div>
                </button>
                
                <button 
                    wire:click="$set('showConfirmModal', false)" 
                    class="w-full py-2 text-gray-400 hover:text-gray-600 font-semibold transition">
                    Belum, Batal
                </button>
            </div>
        </div>
    </div>
    @endif
</div>