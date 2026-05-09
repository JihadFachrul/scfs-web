<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Models\Withdrawal;
use App\Models\MerchantProfile;

new 
#[Layout('layouts.lkbb')] 
class extends Component {
    
    public string $alasanTolak = '';
    public ?int $selectedWithdrawalId = null;
    public bool $isRejectModalOpen = false;

    // Ambil semua data penarikan yang statusnya masih 'pending'
    #[Computed]
    public function pendingWithdrawals()
    {
        return Withdrawal::whereNotNull('merchant_id')
                ->where('status', 'pending')
                ->latest()
                ->get();
    }

    // Fungsi jika LKBB klik "Setujui"
    public function approve($id)
    {
        try {
            $wd = Withdrawal::findOrFail($id);
            
            // Ubah status jadi disetujui
            $wd->update([
                'status' => 'disetujui',
                'catatan_lkbb' => 'Dana telah ditransfer ke rekening tujuan.'
            ]);
            
            session()->flash('success', 'Penarikan berhasil disetujui. Pastikan Anda sudah mentransfer uangnya!');
        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    // Buka Modal Penolakan
    public function openRejectModal($id)
    {
        $this->selectedWithdrawalId = $id;
        $this->alasanTolak = '';
        $this->isRejectModalOpen = true;
    }

    // Fungsi jika LKBB klik "Tolak"
    public function reject()
    {
        $this->validate([
            'alasanTolak' => 'required|min:5'
        ], [
            'alasanTolak.required' => 'Alasan penolakan wajib diisi agar merchant tahu.',
            'alasanTolak.min' => 'Alasan penolakan terlalu singkat.'
        ]);

        try {
            DB::transaction(function () {
                $wd = Withdrawal::where('id', $this->selectedWithdrawalId)->lockForUpdate()->firstOrFail();
                
                if ($wd->status !== 'pending') {
                    throw new \Exception('Status penarikan sudah berubah.');
                }

                $merchant = MerchantProfile::where('user_id', $wd->merchant_id)->lockForUpdate()->firstOrFail();

                // REFUND DINAMIS: Kembalikan saldo + kembalikan hutang (jika kemarin bayar hutang)
                $merchant->increment('saldo_token', $wd->nominal_kotor);
                
                if ($wd->potongan_lkbb > 0) {
                    $merchant->increment('tagihan_setoran_tunai', $wd->potongan_lkbb);
                }

                $wd->update([
                    'status' => 'ditolak',
                    'catatan_lkbb' => $this->alasanTolak
                ]);
            });

            $this->isRejectModalOpen = false;
            session()->flash('success', 'Penarikan ditolak. Saldo berhasil dikembalikan ke Merchant.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menolak penarikan: ' . $e->getMessage());
        }
    }
}; ?>

<div class="py-8 px-6 md:px-8 w-full max-w-7xl mx-auto space-y-6">
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Approval Withdraw Merchant</h2>
        <p class="text-gray-500 text-sm mt-1">Tinjau dan setujui permintaan pencairan dana dari kantin.</p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl mb-4">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-bold tracking-wider border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4">Nomor WD / Waktu</th>
                    <th class="px-6 py-4">ID Merchant</th>
                    <th class="px-6 py-4">Info Pencairan</th>
                    <th class="px-6 py-4 text-right">Nominal Penarikan</th>
                    <th class="px-6 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->pendingWithdrawals as $wd)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-mono text-sm text-gray-900 font-bold">{{ $wd->nomor_pencairan }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $wd->created_at->format('d M Y, H:i') }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-700">User ID: {{ $wd->merchant_id }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $wd->info_pencairan }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="text-sm font-extrabold text-emerald-600">Rp {{ number_format($wd->nominal_bersih, 0, ',', '.') }}</div>
                        </td>
                        <td class="px-6 py-4 text-center space-x-2">
                            <button wire:click="approve({{ $wd->id }})" wire:confirm="Anda yakin sudah mentransfer uang ke Merchant ini?" class="bg-emerald-100 text-emerald-700 hover:bg-emerald-200 px-3 py-1.5 rounded-lg text-xs font-bold transition">Setujui</button>
                            <button wire:click="openRejectModal({{ $wd->id }})" class="bg-rose-100 text-rose-700 hover:bg-rose-200 px-3 py-1.5 rounded-lg text-xs font-bold transition">Tolak</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">Tidak ada pengajuan pencairan dana saat ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MODAL PENOLAKAN --}}
    @if($isRejectModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Tolak Pencairan Dana</h3>
                <p class="text-sm text-gray-500 mb-4">Saldo merchant akan dikembalikan seperti semula. Berikan alasan penolakan.</p>
                
                <textarea wire:model="alasanTolak" rows="3" class="w-full border border-gray-300 rounded-xl p-3 text-sm focus:ring-rose-500 focus:border-rose-500" placeholder="Contoh: Nomor rekening tidak ditemukan..."></textarea>
                @error('alasanTolak') <span class="text-xs text-rose-500 font-bold block mt-1">{{ $message }}</span> @enderror

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="$set('isRejectModalOpen', false)" class="px-4 py-2 text-sm font-bold text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200">Batal</button>
                    <button wire:click="reject" class="px-4 py-2 text-sm font-bold text-white bg-rose-600 rounded-xl hover:bg-rose-700">Konfirmasi Tolak</button>
                </div>
            </div>
        </div>
    @endif
</div>