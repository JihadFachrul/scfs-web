<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\MerchantProfile;
use App\Models\MahasiswaProfile;
use App\Models\Transaction;
use App\Models\Wallet;
use Carbon\Carbon;

new 
#[Layout('layouts.app')]
class extends Component {
    
    public string $chartFilter = 'month';

    public function mount()
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Akses Ditolak. Halaman ini khusus Administrator.');
        }
    }

    #[Computed]
    public function stats()
    {
        // 1. DATA 3 AKTOR UTAMA (Verifikasi Sukses / Aktif)
        $mahasiswaAktif = MahasiswaProfile::where('status_verifikasi', 'disetujui')->count();
        $merchantAktif = MerchantProfile::where('status_verifikasi', 'disetujui')->count();
        // Menghitung total pemasok (Asumsi mengambil dari role users)
        $pemasokAktif = User::where('role', 'pemasok')->count(); 

        // 2. DATA TRANSAKSI & PERPUTARAN EKOSISTEM
        $transaksiSukses = Transaction::whereIn('status', ['lunas', 'sukses', 'success']);
        $totalPerputaran = (clone $transaksiSukses)->sum('total_amount');
        $transaksiHariIni = Transaction::whereDate('created_at', Carbon::today())->count();

        // 3. DATA MONITORING BRANKAS LKBB
        $saldoInvestasi = Wallet::where('type', 'LKBB_INVESTMENT')->sum('balance');
        $saldoDonasi = Wallet::where('type', 'LKBB_DONATION')->sum('balance');

        return [
            'mahasiswa'          => $mahasiswaAktif,
            'merchant'           => $merchantAktif,
            'pemasok'            => $pemasokAktif,
            'total_perputaran'   => $totalPerputaran,
            'transaksi_hari_ini' => $transaksiHariIni,
            'saldo_investasi'    => $saldoInvestasi,
            'saldo_donasi'       => $saldoDonasi,
        ];
    }

    #[Computed]
    public function recentActivities()
    {
        return Transaction::with('user')
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($trx) {
                $typeName = $trx->type === 'INJEKSI_MANUAL' ? 'Injeksi Saldo LKBB' : ucwords(str_replace('_', ' ', $trx->type ?? 'Transaksi'));
                $actorName = $trx->type === 'INJEKSI_MANUAL' ? 'Admin LKBB / Sistem' : ($trx->user->name ?? 'Sistem / Guest');
                
                $statusTrx = in_array(strtolower($trx->status), ['lunas', 'sukses', 'success']) ? 'Selesai' : (strtolower($trx->status) == 'pending' ? 'Tertunda' : 'Gagal');
                $avatar = $trx->type === 'INJEKSI_MANUAL' ? '🏦' : strtoupper(substr($trx->user->name ?? 'S', 0, 2));

                return [
                    'name'   => $actorName,
                    'id'     => $trx->order_id ?? ('TRX-' . $trx->id),
                    'type'   => $typeName,
                    'status' => $statusTrx,
                    'amount' => $trx->total_amount,
                    'time'   => $trx->created_at->diffForHumans(),
                    'avatar' => $avatar
                ];
            });
    }

    public function getChartData()
    {
        $query = Transaction::whereIn('status', ['lunas', 'sukses', 'success']);
        
        $labels = [];
        $series = []; 

        if ($this->chartFilter === 'today') {
            $txs = (clone $query)->whereDate('created_at', Carbon::today())->get();
            $grouped = $txs->groupBy(fn($item) => Carbon::parse($item->created_at)->format('H'));

            for ($i = 6; $i <= 21; $i++) {
                $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
                $labels[] = $hour . ':00';
                $series[] = $grouped->has($hour) ? (int) $grouped->get($hour)->sum('total_amount') : 0;
            }
        } elseif ($this->chartFilter === 'month') {
            $now = Carbon::now();
            $txs = (clone $query)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->get();
            $grouped = $txs->groupBy(fn($item) => Carbon::parse($item->created_at)->format('j'));

            for ($i = 1; $i <= $now->daysInMonth; $i++) {
                $labels[] = $i . ' ' . $now->format('M');
                $series[] = $grouped->has((string)$i) ? (int) $grouped->get((string)$i)->sum('total_amount') : 0;
            }
        } elseif ($this->chartFilter === 'year') {
            $now = Carbon::now();
            $txs = (clone $query)->whereYear('created_at', $now->year)->get();
            $grouped = $txs->groupBy(fn($item) => Carbon::parse($item->created_at)->format('n'));

            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            for ($i = 1; $i <= 12; $i++) {
                $labels[] = $months[$i - 1];
                $series[] = $grouped->has((string)$i) ? (int) $grouped->get((string)$i)->sum('total_amount') : 0;
            }
        }

        return ['labels' => $labels, 'series' => $series];
    }

    public function setFilter($filter)
    {
        $this->chartFilter = $filter;
        $data = $this->getChartData();
        $this->dispatch('update-admin-chart', labels: $data['labels'], series: $data['series']);
    }

}; ?>

<div class="space-y-8 font-sans text-gray-800 p-6 md:p-8 w-full relative bg-gray-50/50 min-h-screen">
    
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    {{-- HEADER --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-[#0A60B3] tracking-tight">Executive Dashboard</h2>
            <p class="text-gray-500 mt-1 font-medium">Ringkasan performa 3 aktor utama dan monitoring aliran dana ekosistem SCFS ITB.</p>
        </div>
        <button class="bg-white text-[#0A60B3] border-2 border-[#0A60B3] px-5 py-2.5 rounded-xl text-sm font-extrabold hover:bg-[#0A60B3] hover:text-white transition shadow-lg flex items-center gap-2 group">
            <svg class="w-5 h-5 group-hover:animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Unduh Laporan
        </button>
    </div>

    {{-- ROW 1: METRIK 3 AKTOR UTAMA (MAHASISWA, MERCHANT, PEMASOK) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div class="bg-gradient-to-br from-blue-600 to-[#0A60B3] p-6 rounded-2xl shadow-xl shadow-blue-200 flex flex-col relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10 transition-transform duration-500 group-hover:scale-110"></div>
            <div class="flex justify-between items-start z-10 mb-4">
                <div class="h-12 w-12 rounded-xl bg-white/20 text-white flex items-center justify-center backdrop-blur-sm">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v6" /></svg>
                </div>
                <span class="bg-blue-900/40 text-blue-100 text-[10px] font-extrabold px-2 py-1 rounded-lg uppercase tracking-wider backdrop-blur-sm">Aktor 1</span>
            </div>
            <div class="z-10">
                <p class="text-xs font-bold text-blue-200 uppercase tracking-wider mb-1">Mahasiswa Terverifikasi</p>
                <h3 class="text-3xl font-black text-white">{{ number_format($this->stats['mahasiswa'], 0, ',', '.') }} <span class="text-sm font-medium text-blue-200">Akun</span></h3>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-amber-600 p-6 rounded-2xl shadow-xl shadow-orange-200 flex flex-col relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10 transition-transform duration-500 group-hover:scale-110"></div>
            <div class="flex justify-between items-start z-10 mb-4">
                <div class="h-12 w-12 rounded-xl bg-white/20 text-white flex items-center justify-center backdrop-blur-sm">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                </div>
                <span class="bg-orange-900/40 text-orange-100 text-[10px] font-extrabold px-2 py-1 rounded-lg uppercase tracking-wider backdrop-blur-sm">Aktor 2</span>
            </div>
            <div class="z-10">
                <p class="text-xs font-bold text-orange-200 uppercase tracking-wider mb-1">Total Kantin / Merchant</p>
                <h3 class="text-3xl font-black text-white">{{ number_format($this->stats['merchant'], 0, ',', '.') }} <span class="text-sm font-medium text-orange-200">Toko</span></h3>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-600 to-indigo-700 p-6 rounded-2xl shadow-xl shadow-purple-200 flex flex-col relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10 transition-transform duration-500 group-hover:scale-110"></div>
            <div class="flex justify-between items-start z-10 mb-4">
                <div class="h-12 w-12 rounded-xl bg-white/20 text-white flex items-center justify-center backdrop-blur-sm">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14H5a2 2 0 00-2 2v.5a1.5 1.5 0 001.5 1.5h1.5m8-4h3a2 2 0 012 2v.5a1.5 1.5 0 01-1.5 1.5h-1.5m-8-4v-4a2 2 0 012-2h4a2 2 0 012 2v4m-8 4v2m8-4v2M8 18a2 2 0 100-4 2 2 0 000 4zm8 0a2 2 0 100-4 2 2 0 000 4z" /></svg>
                </div>
                <span class="bg-purple-900/40 text-purple-100 text-[10px] font-extrabold px-2 py-1 rounded-lg uppercase tracking-wider backdrop-blur-sm">Aktor 3</span>
            </div>
            <div class="z-10">
                <p class="text-xs font-bold text-purple-200 uppercase tracking-wider mb-1">Total Pemasok (Supplier)</p>
                <h3 class="text-3xl font-black text-white">{{ number_format($this->stats['pemasok'], 0, ',', '.') }} <span class="text-sm font-medium text-purple-200">Mitra</span></h3>
            </div>
        </div>

    </div>

    {{-- ROW 2: KEUANGAN & MONITORING --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex items-center gap-5">
            <div class="h-14 w-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
            <div class="w-full">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Perputaran Dana Sukses</p>
                <h3 class="text-2xl font-black text-gray-900 truncate">Rp {{ number_format($this->stats['total_perputaran'], 0, ',', '.') }}</h3>
            </div>
        </div>

        <div class="bg-white border-l-4 border-[#0A60B3] p-6 rounded-2xl shadow-sm flex flex-col justify-center">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Saldo Brankas Investasi (LKBB)</p>
                <span class="bg-blue-100 text-[#0A60B3] text-[9px] px-2 py-1 rounded-md font-extrabold uppercase">Pantau</span>
            </div>
            <h3 class="text-2xl font-black text-[#0A60B3] truncate">Rp {{ number_format($this->stats['saldo_investasi'], 0, ',', '.') }}</h3>
        </div>

        <div class="bg-white border-l-4 border-amber-500 p-6 rounded-2xl shadow-sm flex flex-col justify-center">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Saldo Brankas Donasi (LKBB)</p>
                <span class="bg-amber-100 text-amber-700 text-[9px] px-2 py-1 rounded-md font-extrabold uppercase">Pantau</span>
            </div>
            <h3 class="text-2xl font-black text-amber-600 truncate">Rp {{ number_format($this->stats['saldo_donasi'], 0, ',', '.') }}</h3>
        </div>
    </div>

    {{-- ROW 3: GRAFIK & RIWAYAT TRANSAKSI --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- GRAFIK ADMIN LAPI (DISEMPURNAKAN) --}}
        <div class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
    
            <div class="px-6 py-5 border-b border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gray-50/50">
                <div>
                    <h3 class="font-black text-gray-900 text-lg">Grafik Perputaran Ekosistem</h3>
                    <p class="text-sm text-gray-500 font-medium">Lacak total volume transaksi berhasil di SCFS</p>
                </div>
                
                <div class="inline-flex bg-gray-200/60 p-1.5 rounded-xl border border-gray-100">
                    <button wire:click="setFilter('today')" class="px-4 py-2 text-xs font-extrabold rounded-lg transition-all {{ $chartFilter === 'today' ? 'bg-white text-[#0A60B3] shadow-md' : 'text-gray-500 hover:text-[#0A60B3]' }}">Hari Ini</button>
                    <button wire:click="setFilter('month')" class="px-4 py-2 text-xs font-extrabold rounded-lg transition-all {{ $chartFilter === 'month' ? 'bg-white text-[#0A60B3] shadow-md' : 'text-gray-500 hover:text-[#0A60B3]' }}">Bulan Ini</button>
                    <button wire:click="setFilter('year')" class="px-4 py-2 text-xs font-extrabold rounded-lg transition-all {{ $chartFilter === 'year' ? 'bg-white text-[#0A60B3] shadow-md' : 'text-gray-500 hover:text-[#0A60B3]' }}">Tahun Ini</button>
                </div>
            </div>
            
            <div class="p-6 flex-1 w-full relative">
                <div 
                    x-data="{
                        chart: null,
                        initChart() {
                            let options = {
                                chart: { type: 'area', height: 340, width: '100%', fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false } },
                                series: [{ name: 'Volume Transaksi (Rp)', data: [] }],
                                colors: ['#0A60B3'], // Corporate LAPI Blue
                                stroke: { curve: 'smooth', width: 4 },
                                markers: { size: 0, hover: { size: 6 } },
                                fill: { 
                                    type: 'gradient',
                                    gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.5, gradientToColors: ['#137FEC'], opacityFrom: 0.5, opacityTo: 0.05, stops: [0, 100] }
                                },
                                xaxis: { categories: [], tooltip: { enabled: false }, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#6B7280', fontWeight: 600 } } },
                                yaxis: { labels: { style: { colors: '#6B7280', fontWeight: 600 }, formatter: function(val) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(val); } } },
                                grid: { strokeDashArray: 5, borderColor: '#F3F4F6', padding: { top: 0, right: 0, bottom: 0, left: 15 } },
                                tooltip: { theme: 'light', y: { formatter: function(val) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(val); } } },
                                dataLabels: { enabled: false }
                            };

                            this.chart = new ApexCharts(this.$refs.adminChart, options);
                            this.chart.render();

                            // Load Initial Data
                            $wire.getChartData().then(data => {
                                this.chart.updateOptions({ xaxis: { categories: data.labels } });
                                this.chart.updateSeries([{ data: data.series }]);
                            });
                        }
                    }"
                    x-init="initChart()"
                    @update-admin-chart.window="
                        chart.updateOptions({ xaxis: { categories: $event.detail.labels } });
                        chart.updateSeries([{ data: $event.detail.series }]);
                    "
                >
                    <div x-ref="adminChart" wire:ignore class="w-full"></div>
                </div>
            </div>
        </div>

        {{-- AKTIVITAS TERBARU (LIVE) --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col h-full lg:col-span-1 overflow-hidden relative">
            <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div>
                    <h3 class="text-base font-black text-gray-900">Aktivitas Live</h3>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">{{ $this->stats['transaksi_hari_ini'] }} Hari ini</p>
                    </div>
                </div>
            </div>
            
            <div class="flex-1 overflow-y-auto p-3 space-y-1">
                @forelse($this->recentActivities as $activity)
                <div class="flex items-center justify-between p-3 hover:bg-gray-50/80 rounded-xl transition group border border-transparent hover:border-gray-100">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-10 w-10 rounded-full {{ $activity['type'] == 'Injeksi Saldo LKBB' ? 'bg-indigo-100 text-indigo-600 border border-indigo-200' : 'bg-blue-50 text-[#0A60B3] border border-blue-100' }} flex items-center justify-center text-sm font-extrabold flex-shrink-0 shadow-sm">
                            {{ $activity['avatar'] }}
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-gray-800 text-sm truncate group-hover:text-[#0A60B3] transition">
                                {{ $activity['name'] }}
                            </div>
                            <div class="text-[10px] text-gray-500 truncate font-semibold uppercase">
                                {{ $activity['type'] }} &bull; <span class="normal-case">{{ $activity['time'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-2">
                        <div class="text-[13px] font-black text-gray-900">
                            Rp {{ number_format($activity['amount'], 0, ',', '.') }}
                        </div>
                        @if($activity['status'] == 'Selesai')
                            <span class="text-[9px] font-extrabold text-emerald-600 uppercase tracking-widest bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded shadow-sm mt-1 inline-block">Selesai</span>
                        @elseif($activity['status'] == 'Tertunda')
                            <span class="text-[9px] font-extrabold text-orange-600 uppercase tracking-widest bg-orange-50 border border-orange-100 px-2 py-0.5 rounded shadow-sm mt-1 inline-block">Pending</span>
                        @else
                            <span class="text-[9px] font-extrabold text-rose-600 uppercase tracking-widest bg-rose-50 border border-rose-100 px-2 py-0.5 rounded shadow-sm mt-1 inline-block">Gagal</span>
                        @endif
                    </div>
                </div>
                @empty
                <div class="text-center py-12 flex flex-col items-center justify-center text-gray-400">
                    <div class="text-4xl mb-3 opacity-30 grayscale">📊</div>
                    <p class="text-sm font-bold">Ekosistem sedang sepi.</p>
                </div>
                @endforelse
            </div>
        </div>

    </div>
</div>