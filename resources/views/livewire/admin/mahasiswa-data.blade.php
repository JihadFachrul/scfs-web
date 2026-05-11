<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanBantuan; 
use Illuminate\Support\Facades\Hash;

new 
#[Layout('layouts.app')] 
class extends Component {
    
    public $search = '';
    public $activeTab = 'Semua'; 

    // Variabel Form Tambah Mahasiswa
    public $isAddModalOpen = false;
    public $nama_lengkap, $nim, $jurusan, $no_hp, $alamat, $semester, $ipk;
    public $email, $password; 

    // --- VARIABEL BARU: FORM PENGAJUAN NOMINAL ---
    public $isAjukanModalOpen = false;
    public $ajukanUserId = null;
    public $ajukanNama = '';
    public $ajukanNominal = '500.000'; // Default string format rupiah

    public function getStudentsProperty()
    {
        $query = User::where('role', 'mahasiswa')
            ->whereHas('mahasiswaProfile', function($q) {
                $q->where('status_verifikasi', 'disetujui');
            })->with('mahasiswaProfile');

        if ($this->activeTab !== 'Semua') {
            $status = strtolower(str_replace(' ', '_', $this->activeTab));
            $query->whereHas('mahasiswaProfile', function($q) use ($status) {
                if ($status == 'belum_diajukan') {
                    $q->whereNull('status_bantuan')->orWhere('status_bantuan', 'belum_diajukan');
                } else {
                    $q->where('status_bantuan', $status);
                }
            });
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('mahasiswaProfile', function($q2) {
                      $q2->where('nim', 'like', '%' . $this->search . '%')
                         ->orWhere('jurusan', 'like', '%' . $this->search . '%');
                  });
            });
        }

        return $query->latest()->get();
    }

    // =====================================
    // FUNGSI PENGAJUAN BANTUAN (REVISI)
    // =====================================
    public function openAjukanModal($userId, $nama)
    {
        $this->ajukanUserId = $userId;
        $this->ajukanNama = $nama;
        $this->ajukanNominal = '500.000'; // Reset ke default tiap buka modal
        $this->isAjukanModalOpen = true;
    }

    public function closeAjukanModal()
    {
        $this->isAjukanModalOpen = false;
        $this->reset(['ajukanUserId', 'ajukanNama', 'ajukanNominal']);
    }

    public function submitAjukan()
    {
        // Bersihkan titik dari input string
        $cleanNominal = str_replace('.', '', $this->ajukanNominal);

        if (!is_numeric($cleanNominal) || $cleanNominal < 10000) {
            $this->addError('ajukanNominal', 'Minimal pengajuan Rp 10.000');
            return;
        }

        $user = User::with('mahasiswaProfile')->find($this->ajukanUserId);
        
        if ($user && $user->mahasiswaProfile) {
            $profil = $user->mahasiswaProfile;
            
            // 1. Buat record baru di tabel pengajuan_bantuans dengan nominal bersih
            PengajuanBantuan::create([
                'mahasiswa_profile_id' => $profil->id,
                'nominal' => $cleanNominal,
                'status' => 'diajukan',
                'nomor_pengajuan' => 'SC-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT)
            ]);
            
            // 2. Timpa status bantuan saat ini menjadi 'diajukan' (masuk antrean LKBB lagi)
            $profil->update(['status_bantuan' => 'diajukan']);
        }

        $this->closeAjukanModal();
        session()->flash('message', 'Pengajuan dana sebesar Rp ' . number_format($cleanNominal, 0, ',', '.') . ' berhasil dikirim ke antrean LKBB.');
    }

    // =====================================
    // FUNGSI TAMBAH DATA MAHASISWA BARU
    // =====================================
    public function openAddModal()
    {
        $this->resetForm();
        $this->isAddModalOpen = true;
    }

    public function closeAddModal()
    {
        $this->isAddModalOpen = false;
    }

    public function resetForm()
    {
        $this->reset(['nama_lengkap', 'nim', 'jurusan', 'no_hp', 'alamat', 'semester', 'ipk', 'email', 'password']);
    }

    public function saveMahasiswa()
    {
        $this->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nim' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'ipk' => 'nullable|numeric|min:0|max:4.00',
        ]);

        $user = User::create([
            'name' => $this->nama_lengkap,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => 'mahasiswa',
        ]);

        MahasiswaProfile::create([
            'user_id' => $user->id,
            'nim' => $this->nim,
            'jurusan' => $this->jurusan,
            'no_hp' => $this->no_hp,
            'alamat' => $this->alamat,
            'semester' => $this->semester,
            'ipk' => $this->ipk ? (float) $this->ipk : null,
            'status_verifikasi' => 'disetujui',
            'status_bantuan' => 'belum_diajukan',
            'saldo' => 0,
        ]);

        $this->closeAddModal();
        session()->flash('message', 'Data mahasiswa berhasil ditambahkan.');
    }
}; ?>

<div class="py-12 px-6 md:px-8 w-full space-y-6 relative">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Buku Induk Mahasiswa</h2>
            <p class="text-gray-500 text-sm mt-1">Kelola data mahasiswa aktif, pantau saldo, dan ajukan distribusi donasi berkali-kali ke LKBB.</p>
        </div>
        
        <button wire:click="openAddModal" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm transition flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Tambah Mahasiswa
        </button>
    </div>

    @if (session()->has('message'))
        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg shadow-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
        </div>
    @endif

    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </span>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama, NIM, atau Jurusan..." 
                class="w-full py-2.5 pl-10 pr-4 text-sm text-gray-700 bg-gray-50 border-gray-200 rounded-lg focus:border-blue-500 focus:ring-blue-500 transition">
        </div>

        <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto pb-2 md:pb-0">
            @foreach(['Semua', 'Belum Diajukan', 'Diajukan', 'Disetujui'] as $tab)
                <button 
                    wire:click="$set('activeTab', '{{ $tab }}')"
                    class="px-4 py-2 text-sm rounded-xl transition-all whitespace-nowrap
                    {{ $activeTab === $tab ? 'bg-blue-600 text-white shadow-md font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100 font-medium' }}">
                    {{ $tab }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-bold text-gray-900 text-sm">Daftar Mahasiswa Terverifikasi</h3>
            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full border border-blue-100">Total: {{ $this->students->count() }} Orang</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-white text-gray-500 text-[10px] uppercase font-bold tracking-wider border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4">Nama / NIM</th>
                        <th class="px-6 py-4">Jurusan</th>
                        <th class="px-6 py-4 text-right">Saldo Terkini</th>
                        <th class="px-6 py-4 text-center">Status Transaksi</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($this->students as $student)
                    <tr class="hover:bg-gray-50/80 transition group">
                        
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full flex items-center justify-center text-sm font-bold bg-blue-100 text-blue-600 flex-shrink-0">
                                    {{ strtoupper(substr($student->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900 text-sm">{{ $student->name }}</div>
                                    <div class="text-xs text-gray-400 font-mono">{{ $student->mahasiswaProfile->nim ?? '-' }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-700">{{ $student->mahasiswaProfile->jurusan ?? '-' }}</div>
                            <div class="text-[10px] font-medium text-gray-500 mt-0.5">Smt: {{ $student->mahasiswaProfile->semester ?: '-' }} | IPK: <span class="font-bold text-gray-700">{{ $student->mahasiswaProfile->ipk ?: '-' }}</span></div>
                        </td>

                        <td class="px-6 py-4">
                            <div class="text-sm text-right font-bold {{ ($student->mahasiswaProfile->saldo ?? 0) > 0 ? 'text-green-600' : 'text-gray-400' }}">
                                Rp {{ number_format($student->mahasiswaProfile->saldo ?? 0, 0, ',', '.') }}
                            </div>
                        </td>

                        <td class="px-6 py-4 text-center">
                            @php
                                $statusBantuan = $student->mahasiswaProfile->status_bantuan ?? 'belum_diajukan';
                            @endphp
                            
                            @if($statusBantuan == 'disetujui')
                                <span class="bg-green-100 text-green-700 text-[10px] px-2.5 py-1 rounded-full font-bold uppercase tracking-wider border border-green-200" title="Dana sudah pernah cair">Ready</span>
                            @elseif($statusBantuan == 'diajukan')
                                <span class="bg-blue-100 text-blue-700 text-[10px] px-2.5 py-1 rounded-full font-bold uppercase tracking-wider border border-blue-200 inline-flex items-center gap-1">
                                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg> Menunggu LKBB
                                </span>
                            @elseif($statusBantuan == 'ditolak')
                                <span class="bg-red-100 text-red-700 text-[10px] px-2.5 py-1 rounded-full font-bold uppercase tracking-wider border border-red-200">Ditolak LKBB</span>
                            @else
                                <span class="bg-gray-100 text-gray-500 text-[10px] px-2.5 py-1 rounded-full font-bold uppercase tracking-wider border border-gray-200">Belum Diajukan</span>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                
                                @if($student->mahasiswaProfile->status_bantuan !== 'diajukan')
                                    <button wire:click="openAjukanModal({{ $student->id }}, '{{ $student->name }}')" class="inline-flex items-center px-3 py-1.5 text-[10px] font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors shadow-sm focus:ring-2 focus:ring-blue-200 uppercase tracking-wider">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                        Ajukan Dana
                                    </button>
                                @endif

                                <a href="{{ route('admin.mahasiswa.detail', $student->id) }}" wire:navigate class="inline-flex items-center px-3 py-1.5 text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors uppercase tracking-wider">
                                    Detail
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        <div class="text-4xl mb-3">🎓</div>
                        Belum ada data mahasiswa yang terverifikasi.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($isAjukanModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-blue-50/50">
                <h3 class="font-bold text-gray-900 flex items-center gap-2 text-sm">
                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Ajukan Dana Bantuan
                </h3>
                <button wire:click="closeAjukanModal" class="text-gray-400 hover:text-gray-600 transition-colors p-1.5 rounded-lg hover:bg-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div class="text-center mb-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Penerima Dana</p>
                    <p class="text-lg font-extrabold text-gray-900 mt-1">{{ $ajukanNama }}</p>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Input Nominal Bantuan (Rp)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 font-bold text-gray-500">Rp</span>
                        <input wire:model="ajukanNominal" 
                               type="text" 
                               x-data 
                               x-on:input="$el.value = $el.value.replace(/[^0-9]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
                               class="w-full text-base font-bold text-gray-900 rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 bg-white py-3 pl-12 pr-4 shadow-sm" 
                               placeholder="Contoh: 500.000">
                    </div>
                    @error('ajukanNominal') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                    <p class="text-[10px] text-gray-400 mt-1.5 italic">*Nominal ini akan dikirim ke antrean persetujuan LKBB.</p>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3 bg-gray-50/50">
                <button wire:click="closeAjukanModal" class="w-full px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors focus:ring-4 focus:ring-gray-100">Batal</button>
                <button wire:click="submitAjukan" class="w-full px-4 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors shadow-sm focus:ring-4 focus:ring-blue-100">Kirim Pengajuan</button>
            </div>
        </div>
    </div>
    @endif

    @if($isAddModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm transition-opacity">
        <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
            
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-gray-900 flex items-center gap-2 text-sm">
                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Tambah Data Mahasiswa
                </h3>
                <button wire:click="closeAddModal" class="text-gray-400 hover:text-gray-600 transition-colors p-1.5 rounded-lg hover:bg-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            
            <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nama Lengkap</label>
                        <input wire:model="nama_lengkap" type="text" placeholder="Sesuai KTM" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 bg-white py-2.5">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nomor Induk Mahasiswa (NIM)</label>
                        <input wire:model="nim" type="text" placeholder="Contoh: 13520001" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 bg-white py-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Jurusan</label>
                        <input wire:model="jurusan" type="text" placeholder="Contoh: Informatika" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 bg-white py-2.5">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Semester</label>
                        <input wire:model="semester" type="text" placeholder="Contoh: 5" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 bg-white py-2.5">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">IPK</label>
                        <input wire:model="ipk" type="number" step="0.01" placeholder="Contoh: 3.50" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 bg-white py-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">No Handphone / WA Aktif</label>
                        <input wire:model="no_hp" type="text" placeholder="Contoh: 0812..." class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 bg-white py-2.5">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Alamat Tempat Tinggal</label>
                        <textarea wire:model="alamat" rows="1" placeholder="Alamat kos / rumah" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 bg-white"></textarea>
                    </div>
                </div>

                <hr class="border-gray-100 my-2">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Email Akses Login</label>
                        <input wire:model="email" type="email" placeholder="mahasiswa@itb.ac.id" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 bg-white py-2.5">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Password Sementara</label>
                        <input wire:model="password" type="password" placeholder="Minimal 6 karakter" class="w-full text-sm rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500 bg-white py-2.5">
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50/50">
                <button wire:click="closeAddModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors focus:ring-4 focus:ring-gray-100">Batal</button>
                <button wire:click="saveMahasiswa" class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors shadow-sm focus:ring-4 focus:ring-blue-100">Simpan Data Mahasiswa</button>
            </div>
        </div>
    </div>
    @endif

</div>