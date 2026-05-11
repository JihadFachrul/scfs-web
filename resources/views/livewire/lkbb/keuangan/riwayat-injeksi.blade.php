<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.lkbb')] 
class extends Component {

    public function with()
    {
        return [
            // Kita ambil transaksi bertipe INJEKSI_MANUAL yang dilakukan oleh LKBB ini
            'history' => Transaction::with(['receiverWallet', 'user'])
                ->where('type', 'INJEKSI_MANUAL')
                ->latest()
                ->paginate(10)
        ];
    }
}; ?>

<div class="p-6">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Riwayat Injeksi Saldo</h1>
            <p class="text-gray-500 text-sm mt-1">Daftar seluruh aktivitas pencetakan token (injeksi manual) ke dalam sistem.</p>
        </div>
        <a href="{{ route('lkbb.injeksi-saldo') }}" wire:navigate class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition">
            + Injeksi Baru
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-xs text-gray-400 border-b border-gray-100 uppercase tracking-wider">
                        <th class="py-4 px-6 font-semibold">ID & Tanggal</th>
                        <th class="py-4 px-6 font-semibold">Target Brankas</th>
                        <th class="py-4 px-6 font-semibold">Nominal</th>
                        <th class="py-4 px-6 font-semibold">Keterangan Sumber Dana</th>
                        <th class="py-4 px-6 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-600 divide-y divide-gray-50">
                    @forelse($history as $item)
                    <tr class="hover:bg-gray-50 transition group">
                        <td class="py-4 px-6">
                            <div class="font-bold text-gray-800 group-hover:text-blue-600 transition">{{ $item->order_id }}</div>
                            <div class="text-xs text-gray-400">{{ $item->created_at->format('d M Y, H:i') }} WIB</div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase {{ $item->receiverWallet->type == 'LKBB_INVESTMENT' ? 'bg-indigo-50 text-indigo-600 border border-indigo-100' : 'bg-orange-50 text-orange-600 border border-orange-100' }}">
                                {{ str_replace('_', ' ', $item->receiverWallet->type) }}
                            </span>
                            <div class="text-[10px] text-gray-400 mt-1">Acc: {{ $item->receiverWallet->account_number }}</div>
                        </td>
                        <td class="py-4 px-6">
                            <div class="font-extrabold text-gray-800 text-base">
                                Rp {{ number_format($item->total_amount, 0, ',', '.') }}
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            <div class="max-w-xs text-xs leading-relaxed text-gray-500 italic">
                                "{{ $item->description ?? 'Tidak ada keterangan' }}"
                            </div>
                            <div class="text-[10px] text-blue-500 mt-1 font-medium">Oleh: {{ $item->user->name ?? 'System' }}</div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="bg-green-50 text-green-600 border border-green-100 px-3 py-1 rounded-full text-[10px] font-bold uppercase">
                                Success
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <svg class="w-12 h-12 mb-3 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                <p>Belum ada riwayat injeksi saldo.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($history->hasPages())
        <div class="p-4 border-t border-gray-50 bg-gray-50/30">
            {{ $history->links() }}
        </div>
        @endif
    </div>
</div>