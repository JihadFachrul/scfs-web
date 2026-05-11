<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\PengajuanBantuan;
use App\Models\MahasiswaProfile;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\LedgerEntry; // <-- WAJIB DITAMBAHKAN
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.lkbb')] class extends Component {
    
    public $showDetailModal = false;
    public $selectedPengajuan = null;

    public $showRejectModal = false;
    public $selectedId = null;

    public $showApproveModal = false;

    public $showProfileModal = false;
    public $selectedProfile = null;

    public $statusFilter = 'diajukan'; 

    #[Computed]
    public function filteredRequests()
    {
        return PengajuanBantuan::with(['mahasiswaProfile.user']) 
            ->where('status', $this->statusFilter)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function setFilter($status) { $this->statusFilter = $status; }

    public function lihatDetail($id)
    {
        $this->selectedPengajuan = PengajuanBantuan::with(['mahasiswaProfile.user'])->find($id);
        $this->showDetailModal = true;
    }

    public function tutupDetail() { $this->showDetailModal = false; $this->selectedPengajuan = null; }

    public function lihatProfil($id)
    {
        $this->selectedProfile = MahasiswaProfile::with('user')->find($id);
        $this->showProfileModal = true;
    }

    public function tutupProfil() { $this->showProfileModal = false; $this->selectedProfile = null; }

    public function openApproveModal($id)
    {
        $this->selectedId = $id;
        $this->showApproveModal = true;
        $this->showDetailModal = false; 
    }

    // =====================================
    // FUNGSI APPROVE (REVISI DOMPET BARU)
    // =====================================
    public function confirmApprove()
    {
        try {
            DB::transaction(function () {
                // 1. Kunci data pengajuan (Pessimistic Locking)
                $pengajuan = PengajuanBantuan::with('mahasiswaProfile.user')
                    ->where('id', $this->selectedId)
                    ->where('status', 'diajukan')
                    ->lockForUpdate()
                    ->firstOrFail();

                // 2. Kunci Brankas Donasi LKBB (Nama Tipe Dompet Diperbarui)
                $donationWallet = Wallet::where('type', 'LKBB_DONATION')->lockForUpdate()->first();
                
                if (!$donationWallet || $donationWallet->balance < $pengajuan->nominal) {
                    throw new \Exception('Saldo Brankas Donasi LKBB tidak mencukupi untuk pencairan ini!');
                }

                // 3. Potong Saldo LKBB & Tambah Saldo Mahasiswa
                $donationWallet->decrement('balance', $pengajuan->nominal);
                $pengajuan->mahasiswaProfile->increment('saldo', $pengajuan->nominal);

                // 4. Update status persetujuan
                $pengajuan->update([
                    'status' => 'disetujui',
                    'updated_at' => now(),
                ]);
                $pengajuan->mahasiswaProfile->update(['status_bantuan' => 'disetujui']);

                // 5. Catat Transaksi
                $transaction = Transaction::create([
                    'order_id'         => $pengajuan->nomor_pengajuan,
                    'user_id'          => $pengajuan->mahasiswaProfile->user_id, // ID Mahasiswa Penerima
                    'sender_wallet_id' => $donationWallet->id, // ID Dompet LKBB Pengirim
                    'type'             => 'penerimaan_bantuan',
                    'status'           => 'sukses',
                    'total_amount'     => $pengajuan->nominal,
                    'fee_lkbb'         => 0,
                    'description'      => 'Pencairan Dana Subsidi/Bantuan dari LKBB',
                ]);

                // 6. Catat Mutasi Uang Keluar (DEBIT) di Buku Besar LKBB
                LedgerEntry::create([
                    'transaction_id' => $transaction->id,
                    'wallet_id'      => $donationWallet->id,
                    'entry_type'     => 'DEBIT', // Karena uang keluar dari brankas LKBB
                    'amount'         => $pengajuan->nominal,
                    'balance_after'  => $donationWallet->balance, // Sisa saldo terkini
                ]);
            });

            session()->flash('message', "Dana Bantuan berhasil dicairkan ke dompet mahasiswa!");
            $this->showApproveModal = false;

        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage()); 
            $this->showApproveModal = false;
        }
    }

    public function openRejectModal($id)
    {
        $this->selectedId = $id;
        $this->showRejectModal = true;
        $this->showDetailModal = false; 
    }

    public function confirmReject()
    {
        try {
            DB::transaction(function () {
                $pengajuan = PengajuanBantuan::where('id', $this->selectedId)
                    ->where('status', 'diajukan')
                    ->lockForUpdate()
                    ->firstOrFail();

                $pengajuan->update([
                    'status' => 'ditolak',
                    'updated_at' => now(),
                ]);
                
                MahasiswaProfile::where('id', $pengajuan->mahasiswa_profile_id)
                    ->update(['status_bantuan' => 'ditolak']);
            });

            $this->showRejectModal = false;
            session()->flash('message', 'Pengajuan bantuan berhasil ditolak.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menolak pengajuan.');
            $this->showRejectModal = false;
        }
    }
}; ?>

<div class="py-12 px-6 md:px-8 w-full space-y-6 relative">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Verifikasi Bantuan Mahasiswa</h2>
            <p class="text-gray-500 text-sm mt-1">Daftar mahasiswa yang diajukan oleh Admin LAPI untuk menerima pencairan dana.</p>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg shadow-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
        </div>
    @endif

    <div class="bg-white p-2 rounded-2xl border border-gray-200 shadow-sm inline-flex gap-1 overflow-x-auto max-w-full">
        <button wire:click="setFilter('diajukan')" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-colors whitespace-nowrap {{ $statusFilter == 'diajukan' ? 'bg-yellow-50 text-yellow-600 shadow-sm border border-yellow-100' : 'text-gray-500 hover:bg-gray-50' }}">
            ⏳ Menunggu Persetujuan
        </button>
        <button wire:click="setFilter('disetujui')" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-colors whitespace-nowrap {{ $statusFilter == 'disetujui' ? 'bg-green-50 text-green-600 shadow-sm border border-green-100' : 'text-gray-500 hover:bg-gray-50' }}">
            ✅ Telah Cair
        </button>
        <button wire:click="setFilter('ditolak')" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-colors whitespace-nowrap {{ $statusFilter == 'ditolak' ? 'bg-red-50 text-red-600 shadow-sm border border-red-100' : 'text-gray-500 hover:bg-gray-50' }}">
            ❌ Ditolak
        </button>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-bold text-gray-900 text-sm">
                @if($statusFilter == 'diajukan') Antrean Pencairan @elseif($statusFilter == 'disetujui') Riwayat Pencairan Sukses @else Riwayat Ditolak @endif
            </h3>
            <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full border border-indigo-100">Total: {{ $this->filteredRequests->count() }} Data</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-white text-gray-500 text-[10px] uppercase font-bold tracking-wider border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4">No. Pengajuan</th>
                        <th class="px-6 py-4">Mahasiswa Penerima</th>
                        <th class="px-6 py-4 text-right">Nominal Bantuan</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($this->filteredRequests as $req)
                    <tr class="hover:bg-gray-50/80 transition group">
                        
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-900 text-xs font-mono">{{ $req->nomor_pengajuan }}</div>
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ $req->created_at->format('d M Y, H:i') }}</div>
                        </td>

                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full flex items-center justify-center text-sm font-bold bg-indigo-100 text-[#4338CA] flex-shrink-0">
                                    {{ strtoupper(substr($req->mahasiswaProfile->user->name ?? 'M', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900 text-sm">{{ $req->mahasiswaProfile->user->name ?? 'Tanpa Nama' }}</div>
                                    <div class="text-[10px] text-gray-500 font-mono">NIM: {{ $req->mahasiswaProfile->nim ?? '-' }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 text-right">
                            <div class="text-sm font-bold {{ $statusFilter == 'ditolak' ? 'text-gray-400 line-through' : 'text-indigo-600' }}">
                                Rp {{ number_format($req->nominal ?? 0, 0, ',', '.') }}
                            </div>
                        </td>

                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button wire:click="lihatProfil({{ $req->mahasiswaProfile->id }})" class="px-3 py-1.5 text-[10px] font-bold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition uppercase tracking-wider">
                                    Profil
                                </button>

                                @if($statusFilter == 'diajukan')
                                <button wire:click="lihatDetail({{ $req->id }})" class="px-3 py-1.5 text-[10px] font-bold text-white bg-[#4338CA] rounded-lg hover:bg-indigo-800 transition shadow-sm uppercase tracking-wider">
                                    Review & Cairkan
                                </button>
                                @else
                                <button wire:click="lihatDetail({{ $req->id }})" class="px-3 py-1.5 text-[10px] font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 rounded-lg hover:bg-indigo-100 transition uppercase tracking-wider">
                                    Detail Data
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-16 text-center text-gray-500">
                            <div class="text-4xl mb-3">📬</div>
                            <p class="font-medium text-sm">Tidak ada data di tab ini.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showDetailModal && $selectedPengajuan)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden">
            
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div>
                    <h3 class="font-bold text-gray-900 text-base">Detail Bantuan Mahasiswa</h3>
                    <p class="text-xs text-gray-500 font-mono mt-0.5">{{ $selectedPengajuan->nomor_pengajuan }}</p>
                </div>
                <button wire:click="tutupDetail" class="text-gray-400 hover:text-gray-600 transition-colors p-1.5 rounded-lg hover:bg-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-50 border border-gray-100 p-4 rounded-xl">
                        <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Nama Lengkap</span>
                        <span class="font-bold text-gray-900 text-sm">{{ $selectedPengajuan->mahasiswaProfile->user->name ?? '-' }}</span>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 p-4 rounded-xl">
                        <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">NIM</span>
                        <span class="font-bold text-gray-900 text-sm">{{ $selectedPengajuan->mahasiswaProfile->nim ?? '-' }}</span>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 p-4 rounded-xl">
                        <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Program Studi / Jurusan</span>
                        <span class="font-bold text-gray-900 text-sm">{{ $selectedPengajuan->mahasiswaProfile->jurusan ?? '-' }}</span>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 p-4 rounded-xl">
                        <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">IPK Terakhir</span>
                        <span class="font-bold text-gray-900 text-sm">{{ $selectedPengajuan->mahasiswaProfile->ipk ?? 'Belum diinput' }}</span>
                    </div>
                    
                    <div class="col-span-2 bg-indigo-50 border border-indigo-100 p-5 rounded-xl flex justify-between items-center mt-2">
                        <span class="block text-xs text-indigo-600 font-bold uppercase tracking-wider">Nominal Pencairan</span>
                        <span class="font-extrabold text-indigo-700 text-2xl">Rp {{ number_format($selectedPengajuan->nominal ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50/50">
                @if($selectedPengajuan->status == 'diajukan')
                    <button wire:click="openRejectModal({{ $selectedPengajuan->id }})" class="px-5 py-2.5 text-sm font-bold text-red-600 bg-red-50 border border-red-100 rounded-xl hover:bg-red-100 transition-colors">
                        Tolak Pengajuan
                    </button>
                    <button wire:click="openApproveModal({{ $selectedPengajuan->id }})" class="px-5 py-2.5 text-sm font-bold text-white bg-[#4338CA] rounded-xl hover:bg-indigo-800 transition-colors shadow-sm">
                        Setujui & Cairkan
                    </button>
                @else
                    <button wire:click="tutupDetail" class="px-5 py-2.5 text-sm font-bold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
                        Tutup
                    </button>
                @endif
            </div>
            
        </div>
    </div>
    @endif

    @if($showApproveModal)
    <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden text-center">
            <div class="p-6">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-indigo-100 mb-4">
                    <svg class="h-8 w-8 text-[#4338CA]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Setujui Pencairan Dana?</h3>
                <p class="text-sm text-gray-500">Saldo brankas donasi LKBB akan dipotong dan ditransfer ke Dompet Mahasiswa terkait secara permanen.</p>
            </div>
            <div class="px-6 py-4 flex gap-3 bg-gray-50/50 border-t border-gray-100">
                <button wire:click="$set('showApproveModal', false)" class="w-full px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition">Batal</button>
                <button wire:click="confirmApprove" class="w-full px-4 py-2.5 text-sm font-bold text-white bg-[#4338CA] rounded-xl hover:bg-indigo-800 transition shadow-sm">Ya, Cairkan</button>
            </div>
        </div>
    </div>
    @endif

    @if($showRejectModal)
    <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden text-center">
            <div class="p-6">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Tolak Pengajuan?</h3>
                <p class="text-sm text-gray-500">Apakah Anda yakin ingin menolak pengajuan bantuan ini? Status akan diubah menjadi 'ditolak' secara permanen.</p>
            </div>
            <div class="px-6 py-4 flex gap-3 bg-gray-50/50 border-t border-gray-100">
                <button wire:click="$set('showRejectModal', false)" class="w-full px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition">Batal</button>
                <button wire:click="confirmReject" class="w-full px-4 py-2.5 text-sm font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 transition shadow-sm">Ya, Tolak</button>
            </div>
        </div>
    </div>
    @endif

    @if($showProfileModal && $selectedProfile)
    <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm overflow-y-auto">
        <div class="bg-gray-50 rounded-2xl w-full max-w-4xl shadow-2xl relative my-8 overflow-hidden">
            
            <div class="bg-white px-6 py-4 border-b border-gray-100 flex justify-between items-center sticky top-0 z-10">
                <h3 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#4338CA]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    Profil Mahasiswa Lengkap
                </h3>
                <button wire:click="tutupProfil" class="text-gray-400 hover:text-gray-600 transition-colors p-1.5 rounded-lg hover:bg-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="p-6 space-y-6">
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="h-16 w-16 rounded-2xl bg-indigo-100 text-[#4338CA] flex items-center justify-center text-2xl font-bold border border-indigo-200">
                            {{ strtoupper(substr($selectedProfile->user->name ?? 'M', 0, 2)) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h2 class="text-xl font-extrabold text-gray-900">{{ $selectedProfile->user->name ?? '-' }}</h2>
                                <span class="bg-green-100 text-green-700 text-[9px] px-2 py-0.5 rounded-full font-bold border border-green-200 uppercase tracking-wider">Terverifikasi</span>
                            </div>
                            <p class="text-sm text-gray-500 font-medium">NIM: {{ $selectedProfile->nim ?? '-' }} • {{ $selectedProfile->jurusan ?? '-' }}</p>
                            <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2-2v12a2 2 0 002 2z" /></svg>
                                Bergabung sejak {{ $selectedProfile->created_at ? $selectedProfile->created_at->format('d M Y') : '-' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2 mb-4">Informasi Pribadi</h3>
                        <div class="space-y-4">
                            <div><span class="block text-[10px] text-gray-400 font-bold mb-0.5 uppercase">Email</span><span class="text-gray-900 text-sm font-medium">{{ $selectedProfile->user->email ?? '-' }}</span></div>
                            <div><span class="block text-[10px] text-gray-400 font-bold mb-0.5 uppercase">No HP</span><span class="text-gray-900 text-sm font-medium">{{ $selectedProfile->no_hp ?? '-' }}</span></div>
                            <div><span class="block text-[10px] text-gray-400 font-bold mb-0.5 uppercase">Alamat</span><span class="text-gray-900 text-sm font-medium">{{ $selectedProfile->alamat ?? '-' }}</span></div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2 mb-4">Data Akademik</h3>
                        <div class="space-y-4">
                            <div><span class="block text-[10px] text-gray-400 font-bold mb-0.5 uppercase">Universitas</span><span class="text-gray-900 text-sm font-medium">Institut Teknologi Bandung</span></div>
                            <div><span class="block text-[10px] text-gray-400 font-bold mb-0.5 uppercase">Program Studi</span><span class="text-gray-900 text-sm font-medium">{{ $selectedProfile->jurusan ?? '-' }}</span></div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><span class="block text-[10px] text-gray-400 font-bold mb-0.5 uppercase">Semester</span><span class="text-gray-900 text-sm font-medium">{{ $selectedProfile->semester ?? '-' }}</span></div>
                                <div><span class="block text-[10px] text-gray-400 font-bold mb-0.5 uppercase">IPK Terakhir</span><span class="text-gray-900 text-sm font-medium">{{ $selectedProfile->ipk ?? '-' }}</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-[#4338CA] to-[#312E81] p-6 rounded-2xl shadow-lg text-white flex flex-col justify-between relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-5 rounded-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
                        
                        <div class="relative z-10 flex justify-between items-start mb-4">
                            <div class="p-2 bg-white/20 rounded-xl backdrop-blur-sm">
                                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                            </div>
                            <span class="bg-white/20 text-white text-[9px] px-2.5 py-1 rounded-full font-bold tracking-widest uppercase">
                                e-Wallet
                            </span>
                        </div>

                        <div class="relative z-10">
                            <span class="block text-[10px] text-indigo-200 font-bold tracking-wider mb-1 uppercase">Sisa Saldo Bantuan</span>
                            <span class="text-3xl font-extrabold tracking-tight">Rp {{ number_format($selectedProfile->saldo ?? 0, 0, ',', '.') }}</span>
                        </div>

                        <div class="relative z-10 flex justify-between items-end mt-6 pt-4 border-t border-indigo-400/30">
                            <div>
                                <span class="block text-[9px] text-indigo-200 font-bold uppercase tracking-wider mb-0.5">Pemilik</span>
                                <span class="font-bold text-xs uppercase">{{ $selectedProfile->user->name ?? '-' }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-[10px] text-green-300 flex items-center gap-1.5 justify-end uppercase tracking-wider">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span> Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    @endif

</div>