<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use App\Models\MerchantProfile;
use App\Models\Transaction;
use Carbon\Carbon;

new 
#[Layout('layouts.app')] 
class extends Component {
    use WithFileUploads;

    public $nama_kantin, $nama_pemilik, $nik, $no_hp, $lokasi_blok;

    public $nama_bank = 'BCA';
    public $bank_lainnya = '';
    public $no_rekening = '';
    public $foto_ktp, $foto_kantin;

    // State untuk Filter Grafik (Default: Bulan Ini)
    public string $chartFilter = 'month'; 

    public function mount()
    {
        $profile = MerchantProfile::where('user_id', Auth::id())->first();
        
        if ($profile) {
            $this->nama_kantin = $profile->nama_kantin;
            $this->nama_pemilik = $profile->nama_pemilik;
            $this->nik = $profile->nik;
            $this->no_hp = $profile->no_hp;
            $this->lokasi_blok = $profile->lokasi_blok;
            $standardBanks = ['BCA', 'BNI', 'BRI', 'Mandiri', 'BJB', 'GoPay', 'OVO'];
                if (in_array($profile->nama_bank, $standardBanks)) {
                    $this->nama_bank = $profile->nama_bank;
                } elseif (!empty($profile->nama_bank)) {
                    $this->nama_bank = 'Lainnya';
                    $this->bank_lainnya = $profile->nama_bank;
                }

                $this->no_rekening = $profile->no_rekening;
        } else {
            $this->nama_pemilik = Auth::user()->name;
        }
    }

    #[Computed]
    public function profile()
    {
        return MerchantProfile::firstOrCreate(
            ['user_id' => Auth::id()],
            [
                'status_verifikasi'     => 'belum_melengkapi',
                'nama_pemilik'          => Auth::user()->name,
                'persentase_bagi_hasil' => 0, 
                'saldo_token'           => 0,
                'tagihan_setoran_tunai' => 0,
                'status_toko'           => 'tutup',
            ]
        );
    }

    /**
     * UPDATE: Menghitung Statistik Hari Ini (Menggabungkan Digital & Tunai)
     */
    #[Computed]
    public function statHariIni()
    {
        $today = Carbon::today();
        
        // Base query untuk semua transaksi sukses hari ini
        $baseQuery = Transaction::where('merchant_id', Auth::id())
            ->whereIn('status', ['sukses', 'lunas'])
            ->whereIn('type', ['pembayaran_makanan', 'pembayaran_makanan_tunai'])
            ->whereDate('created_at', $today);

        // Cloning untuk mendapatkan total penjualan (Semua Tipe)
        $penjualanTotal = (clone $baseQuery);
        
        // Cloning khusus untuk menghitung uang tunai yang masuk laci hari ini
        $penjualanTunai = (clone $baseQuery)->where('type', 'pembayaran_makanan_tunai');

        return [
            'total_nominal' => $penjualanTotal->sum('total_amount'),
            'total_pesanan' => $penjualanTotal->count(),
            'uang_laci_hari_ini' => $penjualanTunai->sum('total_amount') // Estimasi Uang Fisik Kasir
        ];
    }

    /**
     * UPDATE: Grafik sekarang menampilkan total (Digital + Tunai)
     */
    public function getChartData()
    {
        $query = Transaction::where('merchant_id', Auth::id())
            ->whereIn('status', ['sukses', 'lunas'])
            ->whereIn('type', ['pembayaran_makanan', 'pembayaran_makanan_tunai']); // Ditambahkan Tunai

        $labels = [];
        $series = [];

        if ($this->chartFilter === 'today') {
            $txs = (clone $query)->whereDate('created_at', Carbon::today())->get();
            $grouped = $txs->groupBy(function($item) {
                return Carbon::parse($item->created_at)->format('H'); 
            });

            for ($i = 6; $i <= 21; $i++) {
                $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
                $labels[] = $hour . ':00';
                $series[] = $grouped->has($hour) ? (int) $grouped->get($hour)->sum('total_amount') : 0;
            }
        } elseif ($this->chartFilter === 'month') {
            $now = Carbon::now();
            $txs = (clone $query)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->get();
            $grouped = $txs->groupBy(function($item) {
                return Carbon::parse($item->created_at)->format('j'); 
            });

            for ($i = 1; $i <= $now->daysInMonth; $i++) {
                $labels[] = $i . ' ' . $now->format('M');
                $series[] = $grouped->has((string)$i) ? (int) $grouped->get((string)$i)->sum('total_amount') : 0;
            }
        } elseif ($this->chartFilter === 'year') {
            $now = Carbon::now();
            $txs = (clone $query)->whereYear('created_at', $now->year)->get();
            $grouped = $txs->groupBy(function($item) {
                return Carbon::parse($item->created_at)->format('n'); 
            });

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
        $this->dispatch('update-chart', labels: $data['labels'], series: $data['series']);
    }

    /**
     * UPDATE: Riwayat menampilkan Digital dan Tunai
     */
    #[Computed]
    public function riwayatTransaksi()
    {
        return Transaction::with('user')
            ->where('merchant_id', Auth::id())
            ->whereIn('status', ['sukses', 'lunas'])
            ->whereIn('type', ['pembayaran_makanan', 'pembayaran_makanan_tunai']) // Ditambahkan Tunai
            ->latest()
            ->take(5)
            ->get();
    }

    public function submitOnboarding()
    {
        $this->validate([
            'nama_kantin'    => 'required|string|max:255',
            'nama_pemilik'   => 'required|string|max:255',
            'nik'            => 'required|digits:16',
            'no_hp'          => 'required|string|max:20',
            'lokasi_blok'    => 'required|string|max:255',
            'nama_bank'      => 'required|string',
            'bank_lainnya'   => 'required_if:nama_bank,Lainnya',
            'no_rekening'    => 'required|numeric',
            'foto_ktp'       => $this->profile->foto_ktp ? 'nullable|image|max:2048' : 'required|image|max:2048', 
            'foto_kantin'    => $this->profile->foto_kantin ? 'nullable|image|max:2048' : 'required|image|max:2048', 
        ]);

        $updateData = [
            'nama_kantin'       => $this->nama_kantin,
            'nama_pemilik'      => $this->nama_pemilik,
            'nik'               => $this->nik,
            'no_hp'             => $this->no_hp,
            'lokasi_blok'       => $this->lokasi_blok,
            'status_verifikasi' => 'menunggu_review', 
            'nama_bank' => $this->nama_bank === 'Lainnya'
                ? $this->bank_lainnya
                : $this->nama_bank,

            'no_rekening' => $this->no_rekening,
        ];

        if ($this->foto_ktp && !is_string($this->foto_ktp)) {
            $updateData['foto_ktp'] = $this->foto_ktp->store('merchants/ktp', 'public');
        }
        if ($this->foto_kantin && !is_string($this->foto_kantin)) {
            $updateData['foto_kantin'] = $this->foto_kantin->store('merchants/kantin', 'public');
        }

        $this->profile->update($updateData);
        session()->flash('message', 'Data berhasil dikirim! Silakan tunggu verifikasi LKBB.');
        unset($this->profile); 
    }

    public function perbaikiData()
    {
        $this->profile->update(['status_verifikasi' => 'belum_melengkapi']);
        unset($this->profile);
    }
}; ?>

<div class="py-8 px-6 md:px-8 w-full space-y-6 relative">

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    @if($this->profile->status_verifikasi === 'belum_melengkapi')
        {{-- BLOK ONBOARDING TETAP SAMA --}}
        <div class="max-w-3xl">
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900">Selamat Datang di SCFS!</h2>
                <p class="text-gray-500 mt-1">Sebelum mulai berjualan, mohon lengkapi profil usaha dan dokumen Anda.</p>
            </div>
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nama Kantin / Usaha</label>
                            <input wire:model="nama_kantin" type="text" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 py-2.5">
                            @error('nama_kantin') <span class="text-[10px] text-red-500 mt-1 font-bold block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Lokasi / Blok Kantin</label>
                            <input wire:model="lokasi_blok" type="text" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 py-2.5">
                            @error('lokasi_blok') <span class="text-[10px] text-red-500 mt-1 font-bold block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nama Pemilik (Sesuai KTP)</label>
                            <input wire:model="nama_pemilik" type="text" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 py-2.5">
                            @error('nama_pemilik') <span class="text-[10px] text-red-500 mt-1 font-bold block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nomor Induk Kependudukan (NIK)</label>
                            <input 
                                wire:model.defer="nik"
                                type="text"
                                maxlength="16"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                oninput="this.value=this.value.replace(/\D/g,'').slice(0,16)"
                                class="w-full py-2.5 px-4 text-sm font-mono border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition"
                            >
                            @error('nik') <span class="text-[10px] text-red-500 mt-1 font-bold block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">No Handphone / WA Aktif</label>
                            <input wire:model="no_hp" type="text" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 py-2.5">
                            @error('no_hp') <span class="text-[10px] text-red-500 mt-1 font-bold block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1.5">
                                Rekening / E-Wallet Pencairan
                            </label>

                            <div class="flex flex-col md:flex-row gap-3">

                                {{-- Jenis Bank --}}
                                <div class="w-full md:w-1/3">
                                    <select wire:model.live="nama_bank"
                                        class="w-full text-sm rounded-xl border-blue-200 focus:border-blue-500 focus:ring-blue-500 py-2.5 bg-blue-50/30">

                                        <option value="BCA">BCA</option>
                                        <option value="BNI">BNI</option>
                                        <option value="BRI">BRI</option>
                                        <option value="Mandiri">Mandiri</option>
                                        <option value="BJB">BJB</option>
                                        <option value="GoPay">GoPay</option>
                                        <option value="OVO">OVO</option>
                                        <option value="Lainnya">Bank Lainnya...</option>
                                    </select>

                                    @error('nama_bank')
                                        <span class="text-[10px] text-red-500 mt-1 font-bold block">
                                            {{ $message }}
                                        </span>
                                    @enderror

                                    @if($nama_bank === 'Lainnya')
                                        <input wire:model="bank_lainnya"
                                            type="text"
                                            placeholder="Nama bank..."
                                            class="mt-2 w-full text-sm rounded-xl border-blue-200 focus:border-blue-500 focus:ring-blue-500 py-2.5 bg-blue-50/30">

                                        @error('bank_lainnya')
                                            <span class="text-[10px] text-red-500 mt-1 font-bold block">
                                                {{ $message }}
                                            </span>
                                        @enderror
                                    @endif
                                </div>

                                {{-- Nomor Rekening --}}
                                <div class="w-full md:w-2/3">
                                    <input wire:model="no_rekening"
                                        type="text"
                                        placeholder="Nomor rekening / nomor e-wallet"
                                        class="w-full text-sm rounded-xl border-blue-200 focus:border-blue-500 focus:ring-blue-500 py-2.5 bg-blue-50/30">

                                    @error('no_rekening')
                                        <span class="text-[10px] text-red-500 mt-1 font-bold block">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>

                            </div>
                        </div>
                    </div>
                    <hr class="border-gray-100 my-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center hover:bg-gray-50 transition relative">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Upload Foto KTP *</label>
                            <input wire:model="foto_ktp" type="file" accept="image/*" class="w-full text-xs text-gray-500 cursor-pointer">
                            @error('foto_ktp') <span class="text-[10px] text-red-500 mt-2 font-bold block">{{ $message }}</span> @enderror
                            @if($foto_ktp && !is_string($foto_ktp))
                                <img src="{{ $foto_ktp->temporaryUrl() }}" class="mt-3 mx-auto h-24 rounded-lg object-cover shadow-sm border border-gray-200">
                            @elseif($this->profile->foto_ktp)
                                <div class="mt-3 text-xs text-green-600 font-bold">✓ Tersimpan</div>
                            @endif
                        </div>
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center hover:bg-gray-50 transition relative">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Upload Foto Depan Kantin *</label>
                            <input wire:model="foto_kantin" type="file" accept="image/*" class="w-full text-xs text-gray-500 cursor-pointer">
                            @error('foto_kantin') <span class="text-[10px] text-red-500 mt-2 font-bold block">{{ $message }}</span> @enderror
                            @if($foto_kantin && !is_string($foto_kantin))
                                <img src="{{ $foto_kantin->temporaryUrl() }}" class="mt-3 mx-auto h-24 rounded-lg object-cover shadow-sm border border-gray-200">
                            @elseif($this->profile->foto_kantin)
                                <div class="mt-3 text-xs text-green-600 font-bold">✓ Tersimpan</div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 text-right">
                    <button wire:click="submitOnboarding" wire:loading.attr="disabled" class="px-6 py-2.5 bg-blue-600 text-white font-bold text-sm rounded-xl shadow-sm hover:bg-blue-700 transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="submitOnboarding">Kirim Pengajuan</span>
                        <span wire:loading wire:target="submitOnboarding">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>

    @elseif($this->profile->status_verifikasi === 'menunggu_review')
        {{-- BLOK WAITING TETAP SAMA --}}
        <div class="max-w-xl mx-auto mt-10">
            <div class="bg-white p-8 rounded-3xl shadow-xl text-center border border-gray-100">
                <div class="w-20 h-20 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center mx-auto mb-6 text-4xl animate-pulse">⏳</div>
                <h2 class="text-2xl font-extrabold text-gray-900 mb-2">Data Sedang Ditinjau</h2>
                <p class="text-gray-500 text-sm leading-relaxed mb-6">Terima kasih telah melengkapi data! Tim LKBB saat ini sedang melakukan proses verifikasi terhadap dokumen kantin Anda. Proses ini biasanya memakan waktu 1x24 Jam.</p>
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-50 text-yellow-700 rounded-full text-xs font-bold border border-yellow-200 uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full bg-yellow-500 animate-ping"></span> Status: Pending Review
                </div>
            </div>
        </div>

    @elseif($this->profile->status_verifikasi === 'ditolak')
        {{-- BLOK REJECT TETAP SAMA --}}
        <div class="max-w-xl mx-auto mt-10">
            <div class="bg-white p-8 rounded-3xl shadow-xl text-center border border-red-100 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1.5 bg-red-500"></div>
                <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-6 text-4xl">❌</div>
                <h2 class="text-2xl font-extrabold text-gray-900 mb-2">Pengajuan Ditolak</h2>
                <p class="text-gray-500 text-sm leading-relaxed mb-4">Mohon maaf, pengajuan pendaftaran merchant Anda dikembalikan oleh LKBB karena alasan berikut:</p>
                <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl text-sm font-medium mb-6 text-left">
                    "{{ $this->profile->catatan_penolakan ?? 'Dokumen tidak lengkap atau foto buram.' }}"
                </div>
                <button wire:click="perbaikiData" class="px-6 py-2.5 bg-gray-900 text-white font-bold text-sm rounded-xl shadow-sm hover:bg-gray-800 transition flex items-center justify-center gap-2 mx-auto">
                    Perbaiki & Kirim Ulang
                </button>
            </div>
        </div>

    @elseif($this->profile->status_verifikasi === 'disetujui')
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Halo, {{ $this->profile->nama_kantin }}! 👋</h2>
                <p class="text-gray-500 text-sm mt-1">Siap melayani mahasiswa hari ini? Buka kasir POS sekarang.</p>
            </div>
            <a href="{{ route('merchant.scan') }}" wire:navigate class="px-4 py-2.5 bg-emerald-600 border border-emerald-200 text-white font-bold text-sm rounded-xl transition shadow-sm flex items-center gap-2 hover:bg-emerald-100 hover:text-emerald-700 group">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                Buka Mesin Kasir POS
            </a>
        </div>

        {{-- UPDATE: GRID STATS SEKARANG 4 KOLOM --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mt-6">
            
            {{-- Card 1: Saldo Digital --}}
            <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl p-5 text-white shadow-lg shadow-blue-200/50 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-white opacity-10 rounded-full -mr-8 -mt-8 pointer-events-none"></div>
                <div class="relative z-10">
                    <p class="text-blue-100 text-[10px] font-bold tracking-wider mb-1">SALDO E-WALLET (DIGITAL)</p>
                    <h3 class="text-2xl font-extrabold tracking-tight truncate">Rp {{ number_format($this->profile->saldo_token ?? 0, 0, ',', '.') }}</h3>
                </div>
            </div>

            {{-- Card 2: Laci Kasir (Fisik) --}}
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl p-5 text-white shadow-lg shadow-emerald-200/50 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-white opacity-10 rounded-full -mr-8 -mt-8 pointer-events-none"></div>
                <div class="relative z-10">
                    <p class="text-emerald-100 text-[10px] font-bold tracking-wider mb-1 uppercase">Estimasi Laci Kasir (Tunai)</p>
                    <h3 class="text-2xl font-extrabold tracking-tight truncate">Rp {{ number_format($this->statHariIni['uang_laci_hari_ini'] ?? 0, 0, ',', '.') }}</h3>
                </div>
            </div>

            {{-- Card 3: Total Volume Hari ini --}}
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center">
                <p class="text-[10px] text-gray-400 font-bold tracking-wider mb-1 uppercase">Total Volume (Hari Ini)</p>
                <h3 class="text-xl font-extrabold text-gray-900 truncate">Rp {{ number_format($this->statHariIni['total_nominal'], 0, ',', '.') }}</h3>
                @if($this->statHariIni['total_pesanan'] > 0)
                    <p class="text-[10px] text-emerald-600 font-bold mt-1.5 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        {{ $this->statHariIni['total_pesanan'] }} Struk Dicetak
                    </p>
                @else
                    <p class="text-[10px] text-gray-400 font-medium mt-1.5">- Belum ada pesanan</p>
                @endif
            </div>

            {{-- Card 4: Hutang LKBB --}}
            <div class="bg-rose-50 border border-rose-100 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                <div class="relative z-10">
                    <p class="text-rose-400 text-[10px] font-bold tracking-wider mb-1 uppercase">Hutang Setoran ({{ $this->profile->persentase_bagi_hasil }}%)</p>
                    <h3 class="text-xl font-extrabold text-rose-700 tracking-tight truncate">Rp {{ number_format($this->profile->tagihan_setoran_tunai ?? 0, 0, ',', '.') }}</h3>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
            
            {{-- AREA GRAFIK (CHART) INTERAKTIF --}}
            <div class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gray-50/50">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm">Grafik Volume Transaksi</h3>
                        <p class="text-[10px] text-gray-500 mt-0.5">Gabungan QR Beasiswa & Tunai Umum</p>
                    </div>
                    
                    {{-- Filter Grafik --}}
                    <div class="inline-flex bg-gray-100 p-1 rounded-lg">
                        <button wire:click="setFilter('today')" class="px-3 py-1.5 text-xs font-bold rounded-md transition {{ $chartFilter === 'today' ? 'bg-white text-emerald-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Hari Ini</button>
                        <button wire:click="setFilter('month')" class="px-3 py-1.5 text-xs font-bold rounded-md transition {{ $chartFilter === 'month' ? 'bg-white text-emerald-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Bulan Ini</button>
                        <button wire:click="setFilter('year')" class="px-3 py-1.5 text-xs font-bold rounded-md transition {{ $chartFilter === 'year' ? 'bg-white text-emerald-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Tahun Ini</button>
                    </div>
                </div>
                
                <div class="p-6 flex-1">
                    <div 
                        x-data="{
                            chart: null,
                            initChart() {
                                let options = {
                                    chart: { type: 'area', height: 320, fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false } },
                                    series: [{ name: 'Volume Jualan (Rp)', data: [] }],
                                    xaxis: { categories: [], tooltip: { enabled: false }, axisBorder: { show: false } },
                                    yaxis: { labels: { formatter: function(val) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(val); } } },
                                    stroke: { curve: 'smooth', width: 3 },
                                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                                    colors: ['#059669'], // Diubah ke Emerald agar sesuai tema POS
                                    dataLabels: { enabled: false },
                                    grid: { strokeDashArray: 4, padding: { top: 0, right: 0, bottom: 0, left: 10 } },
                                    tooltip: { y: { formatter: function(val) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(val); } } }
                                };
                                this.chart = new ApexCharts(this.$refs.chartContainer, options);
                                this.chart.render();

                                let initialData = @js($this->getChartData());
                                this.chart.updateOptions({ xaxis: { categories: initialData.labels } });
                                this.chart.updateSeries([{ data: initialData.series }]);
                            }
                        }"
                        x-init="initChart()"
                        @update-chart.window="
                            chart.updateOptions({ xaxis: { categories: $event.detail.labels } });
                            chart.updateSeries([{ data: $event.detail.series }]);
                        "
                    >
                        <div x-ref="chartContainer" wire:ignore></div>
                    </div>
                </div>
            </div>

            {{-- UPDATE: TABEL RIWAYAT KECIL (MENDUKUNG MULTI-PAYMENT) --}}
            <div class="lg:col-span-1 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <h3 class="font-bold text-gray-900 text-sm">Transaksi Terakhir</h3>
                    <a href="{{ route('merchant.riwayat') }}" wire:navigate class="text-[10px] font-bold text-blue-600 hover:underline">Lihat Semua</a>
                </div>
                <div class="overflow-x-auto flex-1 p-2">
                    <div class="space-y-1">
                        @forelse($this->riwayatTransaksi as $trx)
                        <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition group border border-transparent hover:border-gray-100">
                            <div class="flex items-center gap-3 min-w-0">
                                {{-- Visual Status Icon: Biru untuk Digital, Emas untuk Tunai --}}
                                @if($trx->type === 'pembayaran_makanan_tunai')
                                    <div class="h-9 w-9 rounded-full bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center flex-shrink-0">
                                        <span class="text-sm">💵</span>
                                    </div>
                                @else
                                    <div class="h-9 w-9 rounded-full bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center flex-shrink-0">
                                        <span class="text-sm">💳</span>
                                    </div>
                                @endif
                                
                                <div class="min-w-0">
                                    <div class="font-bold text-sm text-gray-900 truncate">{{ str_replace(['[QR] ', '[TUNAI] '], '', $trx->description) }}</div>
                                    <div class="text-[9px] font-bold mt-0.5 {{ $trx->type === 'pembayaran_makanan_tunai' ? 'text-amber-600' : 'text-blue-600' }}">
                                        {{ $trx->type === 'pembayaran_makanan_tunai' ? 'BAYAR TUNAI' : 'QR BEASISWA' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0 pl-2">
                                <div class="text-sm font-extrabold text-gray-900">Rp{{ number_format($trx->total_amount, 0, ',', '.') }}</div>
                                <div class="text-[9px] text-gray-400 mt-0.5 font-mono">{{ $trx->created_at->format('H:i') }}</div>
                            </div>
                        </div>
                        @empty
                        <div class="py-12 text-center text-gray-400">
                            <div class="text-3xl mb-2 opacity-50">🍽️</div>
                            <p class="text-xs font-medium">Belum ada struk dicetak hari ini.</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>

    @endif
</div>