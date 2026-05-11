<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

new 
#[Layout('layouts.lkbb')] 
class extends Component {

    public function with()
    {
        // ---------------------------------------------------------
        // 1. DATA KARTU (REAL-TIME BALANCE DARI 3 BRANKAS LKBB)
        // ---------------------------------------------------------
        
        // A. DOMPET INVESTASI (Sumber Modal Ekosistem)
        $walletInvestasi = Wallet::where('type', 'LKBB_INVESTMENT')->first();
        $saldoInvestasi = $walletInvestasi ? $walletInvestasi->balance : 0;

        // B. DOMPET DONASI (Sumber Beasiswa Mahasiswa)
        $walletDonasi = Wallet::where('type', 'LKBB_DONATION')->first();
        $saldoDonasi = $walletDonasi ? $walletDonasi->balance : 0;

        // C. DOMPET OPERASIONAL (Keuntungan Bersih LKBB dari Fee Transaksi)
        $walletOperasional = Wallet::where('type', 'LKBB_OPERATIONAL')->first();
        $saldoOperasional = $walletOperasional ? $walletOperasional->balance : 0;

        // D. TOTAL PERPUTARAN UANG (Volume Ekosistem Bulan Ini)
        $trxBulanIni = Transaction::where('status', 'lunas') // Asumsi transaksi sukses
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('total_amount');

        // ---------------------------------------------------------
        // 2. DATA GRAFIK (Cash Flow & Volume)
        // ---------------------------------------------------------
        
        // Grafik 1: Pergerakan Uang di Sistem (6 Bulan Terakhir)
        $cashflowData = Transaction::select(
                DB::raw('SUM(total_amount) as total'),
                DB::raw('MONTH(created_at) as month')
            )
            ->where('status', 'lunas')
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();
        
        $chartLabels = [];
        $chartValues = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $chartLabels[] = $date->format('M');
            $chartValues[] = $cashflowData[$date->month] ?? 0;
        }

        // Grafik 2: Volume Transaksi Harian (7 Hari Terakhir)
        $dailyVolumeData = Transaction::select(
                DB::raw('DATE(created_at) as date'), 
                DB::raw('COUNT(*) as total_trx')
            )
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();
            
        $lineChartLabels = [];
        $lineChartValues = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dayName = Carbon::now()->subDays($i)->isoFormat('ddd');
            
            $record = $dailyVolumeData->firstWhere('date', $date);
            
            $lineChartLabels[] = $dayName;
            $lineChartValues[] = $record ? $record->total_trx : 0;
        }

        // ---------------------------------------------------------
        // 3. TABEL TRANSAKSI TERBARU
        // ---------------------------------------------------------
        $recentTransactions = Transaction::with('user')
            ->latest()
            ->take(5)
            ->get();

        return [
            'saldoInvestasi' => $saldoInvestasi,
            'saldoDonasi' => $saldoDonasi,
            'saldoOperasional' => $saldoOperasional,
            'trxBulanIni' => $trxBulanIni,
            
            // Grafik
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'lineChartLabels' => $lineChartLabels,
            'lineChartValues' => $lineChartValues,
            
            // Tabel
            'recentTransactions' => $recentTransactions,
        ];
    }

    #[Computed]
    public function pendingAlerts()
    {
        return [
            'bantuan_mahasiswa' => \App\Models\PengajuanBantuan::where('status', 'diajukan')->count(), 
            // Opsional: Cek jika ada tabel withdrawal
            'withdrawals' => \App\Models\Withdrawal::where('status', 'pending')->count(), 
            'users' => \App\Models\User::where('role', 'merchant')->whereNull('email_verified_at')->count()
        ];
    }
}; ?>

<div class="p-6">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Dashboard Command Center</h1>
            <p class="text-gray-500 text-sm mt-1">Ringkasan arus kas digital dan operasional ekosistem SCFS.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-2xl p-6 text-white shadow-lg shadow-blue-200 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
            <div class="relative z-10">
                <p class="text-blue-100 text-sm font-medium mb-1">Dompet Investasi (Modal)</p>
                <h3 class="text-3xl font-bold">
                    Rp {{ number_format($saldoInvestasi, 0, ',', '.') }}
                </h3>
                <div class="mt-4 flex items-center gap-2 text-xs bg-blue-800/50 w-fit px-2 py-1 rounded-lg backdrop-blur-sm">
                    <svg class="w-3 h-3 text-blue-300" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9 11H7v-2h2V7h2v2h2v2h-2v2H9v-2z"></path></svg>
                    <span>Dana Siap Suntik</span>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-6 text-white shadow-lg shadow-orange-200 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
            <div class="relative z-10">
                <p class="text-orange-100 text-sm font-medium mb-1">Dompet Donasi (Beasiswa)</p>
                <h3 class="text-3xl font-bold">
                    Rp {{ number_format($saldoDonasi, 0, ',', '.') }}
                </h3>
                <div class="mt-4 flex items-center gap-2 text-xs bg-orange-800/30 w-fit px-2 py-1 rounded-lg backdrop-blur-sm">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Menunggu Disalurkan</span>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-emerald-500 to-green-600 rounded-2xl p-6 text-white shadow-lg shadow-green-200 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
            <div class="relative z-10">
                <p class="text-green-100 text-sm font-medium mb-1">Dompet Operasional (Laba)</p>
                <h3 class="text-3xl font-bold">
                    Rp {{ number_format($saldoOperasional, 0, ',', '.') }}
                </h3>
                <div class="mt-4 flex items-center gap-2 text-xs bg-green-800/30 w-fit px-2 py-1 rounded-lg backdrop-blur-sm">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                    <span>Dari Potongan Admin</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm relative overflow-hidden">
             <div class="absolute top-4 right-4 p-2 bg-purple-100 rounded-lg text-purple-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
            </div>
            <p class="text-gray-500 text-sm font-medium mb-1">Perputaran Bulan Ini</p>
            <h3 class="text-2xl font-bold text-gray-800">
                Rp {{ number_format($trxBulanIni, 0, ',', '.') }}
            </h3>
            <div class="mt-4 flex items-center gap-1 text-xs text-purple-600 font-medium">
                <span>Total ekosistem (Sukses)</span>
            </div>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
    
        @if($this->pendingAlerts['bantuan_mahasiswa'] > 0)
        <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded-r-lg shadow-sm flex items-start justify-between">
            <div class="flex items-center">
                <svg class="w-6 h-6 text-orange-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <h3 class="text-sm font-bold text-orange-800">Pengajuan Beasiswa</h3>
                    <p class="text-xs text-orange-700 mt-1">Ada <strong>{{ $this->pendingAlerts['bantuan_mahasiswa'] }}</strong> pengajuan dana menunggu disetujui.</p>
                </div>
            </div>
            <a href="{{ route('approval.mahasiswa') }}" wire:navigate class="text-xs font-bold text-orange-600 hover:text-orange-800 underline">Setujui</a>
        </div>
        @endif

        @if($this->pendingAlerts['withdrawals'] > 0)
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg shadow-sm flex items-start justify-between">
            <div class="flex items-center">
                <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <div>
                    <h3 class="text-sm font-bold text-blue-800">Permintaan Pencairan (Withdraw)</h3>
                    <p class="text-xs text-blue-700 mt-1">Ada <strong>{{ $this->pendingAlerts['withdrawals'] }}</strong> antrean penarikan dana ke Bank.</p>
                </div>
            </div>
            <a href="#" class="text-xs font-bold text-blue-600 hover:text-blue-800 underline">Proses</a>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm" wire:ignore>
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="font-bold text-gray-800 text-lg">Grafik Perputaran Ekosistem</h3>
                    <p class="text-xs text-gray-400">Total volume uang berputar per bulan</p>
                </div>
            </div>
            <div class="relative h-64 w-full">
                 <canvas id="cashflowChart"></canvas>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm" wire:ignore>
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="font-bold text-gray-800 text-lg">Frekuensi Transaksi Harian</h3>
                    <p class="text-xs text-gray-400">Jumlah aktivitas di aplikasi</p>
                </div>
            </div>
            <div class="relative h-64 w-full">
                 <canvas id="volumeChart"></canvas>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-8">
         <div class="flex justify-between items-center mb-6">
            <h3 class="font-bold text-gray-800 text-lg">Catatan Transaksi Terbaru</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-xs text-gray-400 border-b border-gray-100 uppercase tracking-wider">
                        <th class="py-3 px-4 font-semibold">Tipe Transaksi</th>
                        <th class="py-3 px-4 font-semibold">User Pelaku</th>
                        <th class="py-3 px-4 font-semibold">Tanggal</th>
                        <th class="py-3 px-4 font-semibold">Nominal</th>
                        <th class="py-3 px-4 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-600 divide-y divide-gray-50">
                    @forelse($recentTransactions as $trx)
                    <tr class="hover:bg-gray-50 transition group">
                        <td class="py-4 px-4 font-bold text-gray-700 uppercase text-xs">
                            {{ $trx->type }}
                        </td>
                        <td class="py-4 px-4">
                            <div class="font-medium text-gray-800">{{ optional($trx->user)->name ?? 'Sistem / Guest' }}</div>
                        </td>
                        <td class="py-4 px-4 text-gray-500">{{ $trx->created_at->format('d M Y - H:i') }}</td>
                        <td class="py-4 px-4 font-bold text-gray-800">Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</td>
                        <td class="py-4 px-4">
                            @php
                                $statusClass = match($trx->status) {
                                    'lunas', 'success', 'paid' => 'bg-green-50 text-green-600 border border-green-100',
                                    'pending' => 'bg-yellow-50 text-yellow-600 border border-yellow-100',
                                    'failed' => 'bg-red-50 text-red-600 border border-red-100',
                                    default => 'bg-gray-50 text-gray-600 border border-gray-100'
                                };
                            @endphp
                            <span class="{{ $statusClass }} px-3 py-1 rounded-full text-xs font-bold capitalize">
                                {{ $trx->status }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-8 text-gray-400">Belum ada transaksi di sistem.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('livewire:navigated', () => {
        // --- 1. CONFIG BAR CHART ---
        const ctxBar = document.getElementById('cashflowChart');
        if (ctxBar) {
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: @json($chartLabels),
                    datasets: [{
                        label: 'Volume Berputar (Rp)',
                        data: @json($chartValues),
                        backgroundColor: '#6366F1', // Indigo color
                        borderRadius: 4,
                        barThickness: 25,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [4, 4] } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // --- 2. CONFIG LINE CHART ---
        const ctxLine = document.getElementById('volumeChart');
        if (ctxLine) {
            const gradient = ctxLine.getContext('2d').createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)'); // Emerald color
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

            new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: @json($lineChartLabels),
                    datasets: [{
                        label: 'Jumlah Transaksi',
                        data: @json($lineChartValues),
                        borderColor: '#10B981',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { display: false, beginAtZero: true },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    });
</script>