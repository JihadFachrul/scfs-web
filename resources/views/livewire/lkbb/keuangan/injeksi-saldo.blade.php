<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.lkbb')] 
class extends Component {
    
    public $amount;
    public $target_wallet_id;
    public $description;

    public function mount()
    {
        $userId = Auth::id();
        $types = ['LKBB_DONATION', 'LKBB_INVESTMENT', 'LKBB_OPERATIONAL'];

        foreach ($types as $type) {
            Wallet::firstOrCreate(
                ['user_id' => $userId, 'type' => $type],
                [
                    'account_number' => 'LKBB-' . strtoupper(substr($type, 5, 3)) . '-' . rand(1000, 9999), 
                    'balance' => 0, 
                    'is_active' => true
                ]
            );
        }
    }

    public function with()
    {
        return [
            'wallets' => Wallet::where('user_id', Auth::id())
                ->whereIn('type', ['LKBB_DONATION', 'LKBB_INVESTMENT'])
                ->get()
        ];
    }

    public function inject()
    {
        // 1. BERSIHKAN TITIK DARI INPUTAN (Contoh: "1.000.000" jadi "1000000")
        $cleanAmount = str_replace('.', '', $this->amount);

        // 2. Validasi Data Umum
        $this->validate([
            'target_wallet_id' => 'required|exists:wallets,id',
            'description' => 'required|string|min:5',
        ]);

        // 3. Validasi Khusus Angka Nominal (Mencegah error jika input kosong/salah)
        if (empty($cleanAmount) || !is_numeric($cleanAmount) || $cleanAmount < 1000) {
            $this->addError('amount', 'Nominal tidak valid. Minimal Rp 1.000');
            return;
        }

        try {
            DB::beginTransaction();

            $wallet = Wallet::findOrFail($this->target_wallet_id);

            // Gunakan $cleanAmount yang sudah murni angka untuk database
            $transaction = Transaction::create([
                'user_id' => Auth::id(), 
                'receiver_wallet_id' => $wallet->id,
                'type' => 'INJEKSI_MANUAL',
                'status' => 'success', 
                'total_amount' => $cleanAmount,
                'description' => $this->description,
                'order_id' => 'INJ-' . strtoupper(bin2hex(random_bytes(4))),
            ]);

            $wallet->increment('balance', $cleanAmount);

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
                'entry_type' => 'CREDIT', 
                'amount' => $cleanAmount,
                'balance_after' => $wallet->balance,
            ]);

            DB::commit();

            session()->flash('message', 'Saldo berhasil disuntikkan ke ' . str_replace('_', ' ', $wallet->type));
            $this->reset(['amount', 'target_wallet_id', 'description']);

        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Gagal menyuntikkan saldo: ' . $e->getMessage());
        }
    }
}; ?>

<div class="p-6">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Injeksi Saldo (Minting Token)</h1>
        <p class="text-gray-500 text-sm mt-1">Gunakan halaman ini untuk mengubah uang fisik (Tunai/Bank) menjadi saldo digital di sistem.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                @if (session()->has('message'))
                    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 text-sm font-bold rounded-r-lg">
                        {{ session('message') }}
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm font-bold rounded-r-lg">
                        {{ session('error') }}
                    </div>
                @endif

                <form wire:submit="inject" class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Pilih Brankas Tujuan</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($wallets as $w)
                                <label class="relative flex p-4 cursor-pointer rounded-xl border {{ $target_wallet_id == $w->id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white' }} hover:bg-gray-50 transition">
                                    <input type="radio" wire:model.live="target_wallet_id" value="{{ $w->id }}" class="sr-only">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">{{ str_replace('_', ' ', $w->type) }}</span>
                                        <span class="text-lg font-extrabold text-gray-800">Rp {{ number_format($w->balance, 0, ',', '.') }}</span>
                                    </div>
                                    @if($target_wallet_id == $w->id)
                                        <div class="absolute top-4 right-4 text-blue-600">
                                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000-16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                        </div>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        @error('target_wallet_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Nominal Suntikan (Rp)</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-gray-400 font-bold">Rp</span>
                            
                            <input type="text" 
                                   wire:model="amount" 
                                   x-data 
                                   x-on:input="$el.value = $el.value.replace(/[^0-9]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
                                   class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold text-lg" 
                                   placeholder="Contoh: 1.000.000">
                        </div>
                        @error('amount') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Keterangan / Sumber Dana</label>
                        <textarea wire:model="description" rows="3" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm" placeholder="Contoh: Injeksi Modal dari Investor PT A via Bank Mandiri"></textarea>
                        @error('description') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="w-full py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-200 transition transform active:scale-95 flex justify-center items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Eksekusi Injeksi Saldo
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-indigo-900 rounded-2xl p-6 text-white shadow-xl">
                <h4 class="font-bold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Penting!
                </h4>
                <ul class="text-xs space-y-3 text-indigo-100 leading-relaxed">
                    <li>• Pastikan uang fisik **sudah benar-benar diterima** di rekening bank atau kas tunai LKBB sebelum melakukan injeksi saldo digital.</li>
                    <li>• Setiap injeksi akan tercatat secara permanen di **Buku Besar (Ledger)** dan tidak dapat dihapus demi integritas audit.</li>
                    <li>• **Saldo Investasi** digunakan untuk modal merchant/pemasok.</li>
                    <li>• **Saldo Donasi** digunakan khusus untuk bantuan jajan mahasiswa.</li>
                </ul>
            </div>
        </div>
    </div>
</div>