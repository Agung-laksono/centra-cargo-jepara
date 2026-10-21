<template>
    <Head title="Dokumentasi & Blueprint Sistem" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="font-black text-2xl text-dark tracking-tight flex items-center gap-2.5">
                        <span class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-xl shadow-sm">
                            <i class="ri-book-3-fill"></i>
                        </span>
                        Dokumentasi Sistem Jepara Cargo
                    </h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">
                        Panduan terpadu alur kerja logistik kargo mebel, SOP pengguna lapangan, dan blueprint teknis pengembang.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200/60">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Sistem Aktif &bull; v2.4 Multi-Tenant
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 max-w-7xl mx-auto space-y-6 sm:space-y-8 px-4 sm:px-6">
            
            <!-- Quick Hero & Global Tab Switcher -->
            <div class="bg-gradient-to-br from-white via-slate-50 to-emerald-50/30 rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm relative overflow-hidden">
                <div class="absolute -right-8 -top-8 w-64 h-64 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-primary/10 text-primary text-xs font-bold uppercase tracking-wider mb-2">
                            <i class="ri-compass-3-line"></i> Knowledge Base & SOP
                        </div>
                        <h3 class="text-xl sm:text-2xl font-black text-dark">
                            Pilih Mode Dokumentasi
                        </h3>
                        <p class="text-sm text-gray-600 mt-1 max-w-2xl leading-relaxed">
                            Beralih antara panduan operasional pengguna (kasir, supir, checker gudang) atau arsitektur teknis rekayasa perangkat lunak (database, routes, composables).
                        </p>
                    </div>

                    <!-- Main Tab Selector Buttons -->
                    <div class="flex flex-wrap items-center bg-gray-200/70 p-1.5 rounded-2xl shadow-inner shrink-0 self-start lg:self-center gap-1">
                        <button
                            type="button"
                            @click="activeMainTab = 'user'"
                            :class="[
                                'flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all duration-200',
                                activeMainTab === 'user'
                                    ? 'bg-white text-dark shadow-sm scale-[1.02]'
                                    : 'text-gray-600 hover:text-dark hover:bg-white/40'
                            ]"
                        >
                            <i class="ri-user-star-line text-base sm:text-lg text-primary"></i>
                            <span>Panduan Pengguna (User / SOP)</span>
                        </button>
                        <button
                            type="button"
                            @click="activeMainTab = 'dev'"
                            :class="[
                                'flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all duration-200',
                                activeMainTab === 'dev'
                                    ? 'bg-white text-dark shadow-sm scale-[1.02]'
                                    : 'text-gray-600 hover:text-dark hover:bg-white/40'
                            ]"
                        >
                            <i class="ri-code-s-slash-line text-base sm:text-lg text-blue-600"></i>
                            <span>Developer & Teknis</span>
                        </button>
                        <button
                            type="button"
                            @click="activeMainTab = 'changelog'"
                            :class="[
                                'flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all duration-200',
                                activeMainTab === 'changelog'
                                    ? 'bg-white text-dark shadow-sm scale-[1.02]'
                                    : 'text-gray-600 hover:text-dark hover:bg-white/40'
                            ]"
                        >
                            <i class="ri-history-line text-base sm:text-lg text-amber-600"></i>
                            <span>Riwayat Perkembangan</span>
                        </button>
                    </div>
                </div>

                <!-- Live Quick Search within Docs -->
                <div class="mt-6 pt-6 border-t border-gray-200/60 flex flex-col sm:flex-row items-center gap-3">
                    <div class="relative w-full sm:w-96">
                        <i class="ri-search-2-line absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari fitur, alur kerja, model, atau API..."
                            class="w-full pl-10 pr-4 py-2 text-sm bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition shadow-sm placeholder:text-gray-400"
                        />
                    </div>
                    <span class="text-xs text-gray-500 hidden sm:inline">
                        Menampilkan dokumentasi khusus logistik mebel Jepara ke seluruh Indonesia.
                    </span>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 1: PANDUAN PENGGUNA & OPERASIONAL (USER / SOP) -->
            <!-- ========================================================================= -->
            <div v-if="activeMainTab === 'user'" class="space-y-8">
                
                <!-- 1. SOP Alur Kerja End-to-End -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/60 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-primary uppercase tracking-wider">Tahap Operasional</span>
                            <h3 class="font-black text-lg text-dark flex items-center gap-2 mt-0.5">
                                <i class="ri-git-commit-line text-primary"></i> 1. Alur Kerja Logistik Kargo (End-to-End SOP)
                            </h3>
                        </div>
                        <span class="text-xs text-gray-500 font-medium hidden md:inline">
                            7 Siklus Utama dari Pengrajin hingga Konsumen
                        </span>
                    </div>

                    <div class="p-6 sm:p-8 space-y-8">
                        <div class="relative pl-6 sm:pl-8 border-l-2 border-emerald-200 space-y-10">
                            
                            <!-- Step 1 -->
                            <div class="relative group">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-emerald-50 shadow-sm">
                                    1
                                </div>
                                <div class="bg-slate-50/80 rounded-2xl p-5 border border-slate-100 hover:border-emerald-200 transition-all">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-blue-100 text-blue-800">
                                            Aktor: Kasir / Admin Cabang
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-gray-200 text-gray-700">
                                            Menu: /buat-resi
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-base text-dark">Pencatatan Resi & Pendataan Pengrajin Mebel</h4>
                                    <p class="text-sm text-gray-600 mt-2 leading-relaxed">
                                        Kasir memasukkan data pesanan mebel dari pengrajin (pengirim), penerima, kota tujuan, dan rincian koli/furnitur (misal: Kursi Tamu 3 Koli, Meja Makan 2 Koli). 
                                        Sistem secara otomatis membuat nomor resi unik serta barcode per koli (misal: <code class="text-emerald-700 bg-emerald-50 px-1 py-0.5 rounded font-mono text-xs">JPR-202610-001-K1</code>).
                                    </p>
                                    <div class="mt-3 text-xs text-gray-500 flex items-center gap-4">
                                        <span class="flex items-center gap-1"><i class="ri-checkbox-circle-fill text-emerald-500"></i> Cetak Struk / Label Barcode Thermal</span>
                                        <span class="flex items-center gap-1"><i class="ri-checkbox-circle-fill text-emerald-500"></i> Pilihan Pickup Armada vs Drop Gudang</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2 -->
                            <div class="relative group">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-emerald-50 shadow-sm">
                                    2
                                </div>
                                <div class="bg-slate-50/80 rounded-2xl p-5 border border-slate-100 hover:border-emerald-200 transition-all">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-purple-100 text-purple-800">
                                            Aktor: Koordinator Armada / Dispatcher
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-gray-200 text-gray-700">
                                            Menu: /pickup
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-base text-dark">Penjadwalan Rute & Penugasan Supir Jemput</h4>
                                    <p class="text-sm text-gray-600 mt-2 leading-relaxed">
                                        Barang yang berstatus <em>"Minta Dijemput Armada"</em> otomatis dikelompokkan berdasarkan wilayah/kecamatan bengkel pengrajin (Tahunan, Batealit, Jepara Kota, Mlonggo).
                                        Dispatcher memilih supir jemput, mencentang daftar barang, lalu mengklik <strong>Tugaskan Supir</strong>. Sistem otomatis menghasilkan <em>Magic Link</em> dan tombol kirim WhatsApp instan ke supir.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 3 -->
                            <div class="relative group">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-emerald-50 shadow-sm">
                                    3
                                </div>
                                <div class="bg-slate-50/80 rounded-2xl p-5 border border-slate-100 hover:border-emerald-200 transition-all">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-amber-100 text-amber-800">
                                            Aktor: Supir Pickup (Driver Lapangan)
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-gray-200 text-gray-700">
                                            Tautan: /supir/pickup?token=...
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-base text-dark">Eksekusi Jemput di Bengkel Pengrajin & Scan Barcode</h4>
                                    <p class="text-sm text-gray-600 mt-2 leading-relaxed">
                                        Supir membuka tautan tanpa harus login sandi. Supir melihat daftar bengkel yang harus didatangi, rute jalan via tombol Google Maps, dan men-scan barcode koli mebel saat menaikkannya ke mobil pickup.
                                        Jika seluruh koli ter-scan, status resi langsung terverifikasi menjadi <span class="bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded text-xs font-bold">Diterima / Diangkut</span>.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 4 -->
                            <div class="relative group">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-emerald-50 shadow-sm">
                                    4
                                </div>
                                <div class="bg-slate-50/80 rounded-2xl p-5 border border-slate-100 hover:border-emerald-200 transition-all">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-indigo-100 text-indigo-800">
                                            Aktor: Checker Gudang
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-gray-200 text-gray-700">
                                            Menu: /inbound
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-base text-dark">Inbound Gudang & Sortir Barang Datang</h4>
                                    <p class="text-sm text-gray-600 mt-2 leading-relaxed">
                                        Saat armada penjemputan tiba di Gudang Pusat Jepara, petugas gudang memindai barcode koli yang diturunkan.
                                        Sistem mencocokkan koli yang tiba: jika lengkap, resi berubah menjadi <span class="bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded text-xs font-bold">Di Gudang</span>; jika baru sebagian, status menjadi <span class="bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded text-xs font-bold">Sebagian di Gudang</span>.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 5 -->
                            <div class="relative group">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-emerald-50 shadow-sm">
                                    5
                                </div>
                                <div class="bg-slate-50/80 rounded-2xl p-5 border border-slate-100 hover:border-emerald-200 transition-all">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-rose-100 text-rose-800">
                                            Aktor: Checker Muat Lapangan
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-gray-200 text-gray-700">
                                            Menu: /manifest
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-base text-dark">Muat Truk Ekspedisi Antar-Kota & Terbitkan Manifest</h4>
                                    <p class="text-sm text-gray-600 mt-2 leading-relaxed">
                                        Petugas berdiri di samping bak truk lintas (Fuso/Tronton). Memilih supir atau vendor rekanan serta rute tujuan (misal: Rute Jakarta-Banten atau Rute Jawa Timur/Bali).
                                        Petugas men-scan barcode koli yang dimasukkan ke truk. Dilengkapi counter real-time dan proteksi tombol simpan aman agar tidak salah tertekan. Status resi berubah ke <span class="bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded text-xs font-bold">Dalam Perjalanan</span>.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 6 -->
                            <div class="relative group">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-emerald-50 shadow-sm">
                                    6
                                </div>
                                <div class="bg-slate-50/80 rounded-2xl p-5 border border-slate-100 hover:border-emerald-200 transition-all">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-teal-100 text-teal-800">
                                            Aktor: Supir Lintas & Tim Monitoring
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-gray-200 text-gray-700">
                                            Tautan: /supir/lintas & /lokasi-supir
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-base text-dark">Perjalanan Lintas Kota, Live GPS, & Serah Terima Barang</h4>
                                    <p class="text-sm text-gray-600 mt-2 leading-relaxed">
                                        Supir lintas membawa manifest digital di smartphone. HP supir otomatis memancarkan koordinat lokasi GPS secara periodik ke radar kantor pusat.
                                        Di kota tujuan, supir melakukan verifikasi serah terima, menagih COD (jika ada), dan menyelesaikan pengiriman.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 7 -->
                            <div class="relative group">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-emerald-50 shadow-sm">
                                    7
                                </div>
                                <div class="bg-slate-50/80 rounded-2xl p-5 border border-slate-100 hover:border-emerald-200 transition-all">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-green-100 text-green-800">
                                            Aktor: Pengrajin & Pembeli (Publik)
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-gray-200 text-gray-700">
                                            Halaman: /track-resi/{resi} & Welcome
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-base text-dark">Pelacakan Transparan Real-Time</h4>
                                    <p class="text-sm text-gray-600 mt-2 leading-relaxed">
                                        Pelanggan dan pengrajin dapat melacak status perjalanan kargo mereka secara mandiri tanpa login melalui website pelacakan publik, lengkap dengan timeline histori, status tiap koli, dan data armada.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- 2. Matriks Peran Pengguna (Role Matrix) -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="font-black text-lg text-dark flex items-center gap-2">
                            <i class="ri-shield-user-line text-primary"></i> 2. Panduan Hak Akses & Peran Pengguna (Roles)
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600">
                            <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500">
                                <tr>
                                    <th class="px-6 py-4">Peran (Role)</th>
                                    <th class="px-6 py-4">Tanggung Jawab Utama</th>
                                    <th class="px-6 py-4">Akses Halaman</th>
                                    <th class="px-6 py-4">Metode Masuk</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-medium">
                                <tr class="hover:bg-primary/5 transition-colors">
                                    <td class="px-6 py-4 font-bold text-dark flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> Super Admin
                                    </td>
                                    <td class="px-6 py-4">Pengelola platform induk agregator ekspedisi mebel, pendaftaran mitra ekspedisi, pantau omzet se-Indonesia.</td>
                                    <td class="px-6 py-4">Dashboard Agregator, Mitra Ekspedisi, Pengaturan Sistem Global</td>
                                    <td class="px-6 py-4"><span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">Email & Sandi</span></td>
                                </tr>
                                <tr class="hover:bg-primary/5 transition-colors">
                                    <td class="px-6 py-4 font-bold text-dark flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Admin Cabang
                                    </td>
                                    <td class="px-6 py-4">Input resi, pendataan pengrajin baru, cetak surat jalan resi, kelola kasir dan keuangan cabang.</td>
                                    <td class="px-6 py-4">Buat Resi, Dashboard Cabang, Pengrajin, Settings</td>
                                    <td class="px-6 py-4"><span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">Email & Sandi</span></td>
                                </tr>
                                <tr class="hover:bg-primary/5 transition-colors">
                                    <td class="px-6 py-4 font-bold text-dark flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Petugas Gudang / Checker
                                    </td>
                                    <td class="px-6 py-4">Inbound verifikasi koli tiba dari mobil pickup, penyiapan surat jalan manifest muat truk lintas luar kota.</td>
                                    <td class="px-6 py-4">Inbound Gudang, Manifest Surat Jalan, Barcode Scanner</td>
                                    <td class="px-6 py-4"><span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">Email & Sandi</span></td>
                                </tr>
                                <tr class="hover:bg-primary/5 transition-colors">
                                    <td class="px-6 py-4 font-bold text-dark flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Supir Pickup & Lintas
                                    </td>
                                    <td class="px-6 py-4">Jemput mebel ke pengrajin, scan koli saat muat, kirim barang ke kota tujuan dengan live GPS.</td>
                                    <td class="px-6 py-4">Halaman Supir Pickup & Supir Lintas (Mobile Friendly)</td>
                                    <td class="px-6 py-4"><span class="bg-emerald-100 text-emerald-800 font-bold px-2 py-1 rounded text-xs">Magic Link WA (Tanpa Sandi)</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. Fitur Unggulan UX Lapangan -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="font-black text-lg text-dark flex items-center gap-2">
                            <i class="ri-flashlight-line text-primary"></i> 3. Fitur Khusus Kenyamanan Kerja Lapangan
                        </h3>
                    </div>
                    <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 space-y-2">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-lg shadow-sm">
                                <i class="ri-whatsapp-line"></i>
                            </div>
                            <h4 class="font-bold text-dark">Magic Link WhatsApp</h4>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                Supir tidak perlu repot mengingat akun atau password. Tugas kirim langsung dikirim ke WhatsApp supir berupa tautan pintar berkeamanan token unik.
                            </p>
                        </div>

                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 space-y-2">
                            <div class="w-10 h-10 rounded-xl bg-blue-500 text-white flex items-center justify-center text-lg shadow-sm">
                                <i class="ri-qr-scan-2-line"></i>
                            </div>
                            <h4 class="font-bold text-dark">Scanner Kamera HP + Senter</h4>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                Petugas checker di samping bak truk bisa memindai barcode koli secara cepat menggunakan kamera ponsel, lengkap dengan audio beep dan tombol senter lampu saat malam hari.
                            </p>
                        </div>

                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 space-y-2">
                            <div class="w-10 h-10 rounded-xl bg-purple-500 text-white flex items-center justify-center text-lg shadow-sm">
                                <i class="ri-hand-coin-line"></i>
                            </div>
                            <h4 class="font-bold text-dark">Safety Button & Input Bergantian</h4>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                Tombol input manual dan scan kamera bergantian secara otomatis saat teks diketik, serta proteksi tombol simpan agar tidak sengaja tertekan saat bekerja di samping truk.
                            </p>
                        </div>

                    </div>
                </div>

                <!-- 4. Strategi Perangkat Kerja: Web Browser vs Aplikasi Android (APK) -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/60 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-primary uppercase tracking-wider">Infrastruktur Operasional</span>
                            <h3 class="font-black text-lg text-dark flex items-center gap-2 mt-0.5">
                                <i class="ri-smartphone-line text-primary"></i> 4. Pembagian Perangkat Kerja: Web Browser vs Aplikasi Android (APK)
                            </h3>
                        </div>
                        <span class="text-xs bg-emerald-100 text-emerald-800 font-bold px-2.5 py-1 rounded-lg">
                            Hybrid Deployment
                        </span>
                    </div>
                    <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Web Browser / PWA Card -->
                        <div class="bg-gradient-to-br from-slate-50 to-blue-50/40 p-6 rounded-2xl border border-blue-100 space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-2xl shadow-sm">
                                    <i class="ri-computer-line"></i>
                                </div>
                                <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-blue-100 text-blue-800 font-mono">
                                    Web Browser / Desktop PWA
                                </span>
                            </div>
                            <div>
                                <h4 class="font-bold text-base text-dark">Kantor Pusat, Admin Cabang & Kasir</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Digunakan pada Laptop / Komputer PC Kasir</p>
                            </div>
                            <ul class="text-xs text-gray-600 space-y-2 leading-relaxed">
                                <li class="flex items-start gap-2">
                                    <i class="ri-checkbox-circle-fill text-blue-600 shrink-0 mt-0.5"></i>
                                    <span><strong>Layar Lebar & Tabel Kompleks</strong>: Sangat nyaman untuk input resi massal, filter multi-wilayah, dan pantau radar peta supir.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="ri-checkbox-circle-fill text-blue-600 shrink-0 mt-0.5"></i>
                                    <span><strong>Direct Hardware Printing</strong>: Terhubung langsung ke printer thermal USB kasir (cetak struk resi) dan printer laser/inkjet A4 (cetak surat jalan).</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="ri-checkbox-circle-fill text-blue-600 shrink-0 mt-0.5"></i>
                                    <span><strong>Pembaruan Instan (Zero Maintenance)</strong>: Cukup refresh browser saat ada update fitur tanpa perlu instal ulang aplikasi.</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Android APK Native Card -->
                        <div class="bg-gradient-to-br from-slate-50 to-emerald-50/40 p-6 rounded-2xl border border-emerald-100 space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-2xl shadow-sm">
                                    <i class="ri-android-line"></i>
                                </div>
                                <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 font-mono">
                                    Android APK Native
                                </span>
                            </div>
                            <div>
                                <h4 class="font-bold text-base text-dark">Supir Lapangan & Checker Samping Truk</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Digunakan pada Smartphone Android / Handheld PDA</p>
                            </div>
                            <ul class="text-xs text-gray-600 space-y-2 leading-relaxed">
                                <li class="flex items-start gap-2">
                                    <i class="ri-checkbox-circle-fill text-emerald-600 shrink-0 mt-0.5"></i>
                                    <span><strong>Background Live GPS Supir</strong>: GPS tetap memancarkan koordinat supir meski layar HP terkunci atau saat supir sedang membuka Google Maps.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="ri-checkbox-circle-fill text-emerald-600 shrink-0 mt-0.5"></i>
                                    <span><strong>Scan Barcode Cepat (Google MLKit)</strong>: Memindai barcode kardus mebel yang tertekuk/miring dengan kecepatan milidetik.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="ri-checkbox-circle-fill text-emerald-600 shrink-0 mt-0.5"></i>
                                    <span><strong>WakeLock & Kontrol Hardware</strong>: Layar tidak mati saat ditinggal angkat barang mebel, serta kontrol lampu senter saat muat malam hari.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="ri-checkbox-circle-fill text-emerald-600 shrink-0 mt-0.5"></i>
                                    <span><strong>Offline-First di Pelosok</strong>: Tetap bisa scan koli saat sinyal drop di bengkel pengrajin, otomatis sinkron begitu sinyal kembali.</span>
                                </li>
                            </ul>
                        </div>

                    </div>
                    <div class="px-6 py-4 bg-emerald-50/50 border-t border-emerald-100/60 flex items-center gap-3 text-xs text-emerald-900">
                        <i class="ri-information-line text-emerald-600 text-base shrink-0"></i>
                        <span><strong>Catatan Fleksibilitas (Fallback)</strong>: Supir cadangan atau supir baru tetap dapat menggunakan tautan <em>Magic Link</em> via Web Browser tanpa wajib menginstal APK terlebih dahulu.</span>
                    </div>
                </div>

            </div>

            <!-- ========================================================================= -->
            <!-- TAB 2: DOKUMENTASI PENGEMBANG & TEKNIS (DEVELOPER / TECH) -->
            <!-- ========================================================================= -->
            <div v-if="activeMainTab === 'dev'" class="space-y-8">
                
                <!-- 1. Arsitektur & Tech Stack -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="font-black text-lg text-dark flex items-center gap-2">
                            <i class="ri-cpu-line text-blue-600"></i> 1. Arsitektur Perangkat Lunak & Teknologi (Tech Stack)
                        </h3>
                    </div>
                    <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-4 gap-6">
                        
                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/60 space-y-3">
                            <span class="text-xs font-bold text-rose-600 uppercase tracking-wider">Backend Core</span>
                            <h4 class="font-black text-base text-dark">Laravel & PHP 8.5</h4>
                            <ul class="text-xs text-gray-600 space-y-1.5 list-disc list-inside">
                                <li>Laravel 13.x</li>
                                <li>Inertia.js v2 Adapter</li>
                                <li>Multi-Tenant Global Scopes</li>
                                <li>SQLite & MySQL Supported</li>
                            </ul>
                        </div>

                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/60 space-y-3">
                            <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Frontend SPA</span>
                            <h4 class="font-black text-base text-dark">Vue 3 & Vite</h4>
                            <ul class="text-xs text-gray-600 space-y-1.5 list-disc list-inside">
                                <li>Composition API (<code class="font-mono text-emerald-700">&lt;script setup&gt;</code>)</li>
                                <li>Tailwind CSS Modern Tokens</li>
                                <li>Remix Icon Library</li>
                                <li>Plus Jakarta Sans Typography</li>
                            </ul>
                        </div>

                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/60 space-y-3">
                            <span class="text-xs font-bold text-blue-600 uppercase tracking-wider">Mobile Experience</span>
                            <h4 class="font-black text-base text-dark">PWA & Capacitor/Flutter</h4>
                            <ul class="text-xs text-gray-600 space-y-1.5 list-disc list-inside">
                                <li>Standalone Mobile Web App</li>
                                <li>Native Barcode Scanning MLKit</li>
                                <li>Geolocation Background Watcher</li>
                                <li>Touch Gesture & Swipeable Tabs</li>
                            </ul>
                        </div>

                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/60 space-y-3">
                            <span class="text-xs font-bold text-indigo-600 uppercase tracking-wider">External API</span>
                            <h4 class="font-black text-base text-dark">Wilayah & Telemetri</h4>
                            <ul class="text-xs text-gray-600 space-y-1.5 list-disc list-inside">
                                <li>EMSIFA Wilayah Indonesia API</li>
                                <li>Self-Healing Database Caching</li>
                                <li>WA Click-to-Chat URI Scheme</li>
                                <li>Custom Deep Link (<code class="font-mono text-indigo-700">jeparacargo://</code>)</li>
                            </ul>
                        </div>

                    </div>
                </div>

                <!-- 2. Database Models & State Lifecycle -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="font-black text-lg text-dark flex items-center gap-2">
                            <i class="ri-database-2-line text-blue-600"></i> 2. Model Eloquent & State Machine Siklus Status
                        </h3>
                    </div>
                    <div class="p-6 sm:p-8 space-y-6">
                        
                        <!-- State Flow diagram -->
                        <div class="bg-slate-900 text-white rounded-2xl p-5 sm:p-6 shadow-md overflow-x-auto">
                            <h4 class="text-xs font-mono uppercase tracking-wider text-emerald-400 mb-3 flex items-center gap-2">
                                <i class="ri-route-line"></i> Lifecycle State Machine: Resi (Shipment) & Koli Fisik
                            </h4>
                            <div class="flex items-center gap-2 sm:gap-4 text-xs font-mono min-w-[700px]">
                                <div class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-center">
                                    <div class="text-[10px] text-gray-400">Resi Dibuat</div>
                                    <div class="text-amber-400 font-bold">MENUNGGU_BARANG</div>
                                    <div class="text-[10px] text-gray-400">Koli: MENUNGGU_PICKUP</div>
                                </div>
                                <i class="ri-arrow-right-line text-gray-500 text-lg"></i>
                                <div class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-center">
                                    <div class="text-[10px] text-gray-400">Supir Jemput Muat</div>
                                    <div class="text-blue-400 font-bold">Diterima / DI_ANGKUT</div>
                                    <div class="text-[10px] text-gray-400">Koli: DI_ANGKUT</div>
                                </div>
                                <i class="ri-arrow-right-line text-gray-500 text-lg"></i>
                                <div class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-center">
                                    <div class="text-[10px] text-gray-400">Inbound Gudang</div>
                                    <div class="text-indigo-400 font-bold">Di Gudang</div>
                                    <div class="text-[10px] text-gray-400">Koli: DI_GUDANG</div>
                                </div>
                                <i class="ri-arrow-right-line text-gray-500 text-lg"></i>
                                <div class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-center">
                                    <div class="text-[10px] text-gray-400">Manifest Truk</div>
                                    <div class="text-purple-400 font-bold">Dalam Perjalanan</div>
                                    <div class="text-[10px] text-gray-400">Koli: DALAM_PERJALANAN</div>
                                </div>
                                <i class="ri-arrow-right-line text-gray-500 text-lg"></i>
                                <div class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-center">
                                    <div class="text-[10px] text-gray-400">Serah Terima</div>
                                    <div class="text-emerald-400 font-bold">Selesai</div>
                                    <div class="text-[10px] text-gray-400">Koli: TERKIRIM</div>
                                </div>
                            </div>
                        </div>

                        <!-- Models Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-gray-600">
                                <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Model</th>
                                        <th class="px-4 py-3">Tabel Database</th>
                                        <th class="px-4 py-3">Relasi Utama</th>
                                        <th class="px-4 py-3">Peran Model</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-mono text-xs">
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-dark">Expedition</td>
                                        <td class="px-4 py-3">expeditions</td>
                                        <td class="px-4 py-3">hasMany Users, Warehouses, Shipments</td>
                                        <td class="px-4 py-3 font-sans">Multi-tenant root entity</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-dark">Shipment</td>
                                        <td class="px-4 py-3">shipments</td>
                                        <td class="px-4 py-3">belongsTo Sender, hasMany Kolis, TrackingLogs</td>
                                        <td class="px-4 py-3 font-sans">Header nomor resi muatan mebel</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-dark">Koli</td>
                                        <td class="px-4 py-3">kolis</td>
                                        <td class="px-4 py-3">belongsTo Shipment, belongsToMany Manifests</td>
                                        <td class="px-4 py-3 font-sans">Satuan fisik item dengan barcode ID unik</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-dark">Manifest</td>
                                        <td class="px-4 py-3">manifests</td>
                                        <td class="px-4 py-3">belongsTo Driver, belongsToMany Kolis</td>
                                        <td class="px-4 py-3 font-sans">Surat jalan pengangkutan (PICKUP / DELIVERY)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-dark">Driver</td>
                                        <td class="px-4 py-3">drivers</td>
                                        <td class="px-4 py-3">hasMany Manifests</td>
                                        <td class="px-4 py-3 font-sans">Supir armada + koordinat GPS live tracking</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

                <!-- 3. Daftar Endpoint & API Routes -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="font-black text-lg text-dark flex items-center gap-2">
                            <i class="ri-links-line text-blue-600"></i> 3. Referensi Route & API Endpoint Utama
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600">
                            <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Metode & Route</th>
                                    <th class="px-4 py-3">Auth / Akses</th>
                                    <th class="px-4 py-3">Fungsi & Payload</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-xs">
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-dark">
                                        <span class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded font-bold">GET</span> /track-resi/{resi}
                                    </td>
                                    <td class="px-4 py-3"><span class="bg-gray-100 px-2 py-0.5 rounded text-gray-600 font-medium">Publik</span></td>
                                    <td class="px-4 py-3">API pelacakan resi, mengembalikan JSON timeline status, daftar koli, dan nama pengirim.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-dark">
                                        <span class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded font-bold">GET</span> /supir/pickup?token=...
                                    </td>
                                    <td class="px-4 py-3"><span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-medium">Magic Token</span></td>
                                    <td class="px-4 py-3">Halaman supir jemput mebel ke pengrajin dengan checklist verifikasi barcode.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-dark">
                                        <span class="text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded font-bold">POST</span> /supir/pickup/complete
                                    </td>
                                    <td class="px-4 py-3"><span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-medium">Magic Token</span></td>
                                    <td class="px-4 py-3">Konfirmasi supir memuat koli (support opsi paksa selesai jika koli parsial).</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-dark">
                                        <span class="text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded font-bold">POST</span> /inbound/scan
                                    </td>
                                    <td class="px-4 py-3"><span class="bg-purple-100 text-purple-800 px-2 py-0.5 rounded font-medium">Auth Staff</span></td>
                                    <td class="px-4 py-3">Menerima payload <code class="font-mono">koli_ids[]</code> saat barang tiba di gudang utama.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-dark">
                                        <span class="text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded font-bold">POST</span> /manifest
                                    </td>
                                    <td class="px-4 py-3"><span class="bg-purple-100 text-purple-800 px-2 py-0.5 rounded font-medium">Auth Staff</span></td>
                                    <td class="px-4 py-3">Penerbitan surat jalan manifest lintas, pivot relasi koli ke manifest, dan update shipment status.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-dark">
                                        <span class="text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded font-bold">POST</span> /api/driver/location
                                    </td>
                                    <td class="px-4 py-3"><span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-medium">Driver Token</span></td>
                                    <td class="px-4 py-3">Telemetri live GPS background supir (<code class="font-mono">{ lat, lng, token }</code>).</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. Global Components & Composables -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="font-black text-lg text-dark flex items-center gap-2">
                            <i class="ri-shapes-line text-blue-600"></i> 4. Komponen Global & Vue Composables
                        </h3>
                    </div>
                    <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/60 space-y-2">
                            <div class="flex items-center gap-2 font-mono text-xs font-bold text-dark">
                                <span class="bg-slate-800 text-white px-2 py-0.5 rounded">&lt;BarcodeScanner /&gt;</span>
                                <span class="text-gray-400 font-normal">@/Components/BarcodeScanner.vue</span>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                Komponen pemindai barcode kamera real-time dengan dukungan BarcodeDetector API bawaan browser modern, fallback ZXing, lampu senter (flashlight toggle), dan haptic/audio feedback.
                            </p>
                        </div>

                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/60 space-y-2">
                            <div class="flex items-center gap-2 font-mono text-xs font-bold text-dark">
                                <span class="bg-slate-800 text-white px-2 py-0.5 rounded">&lt;SwipeableTabs /&gt;</span>
                                <span class="text-gray-400 font-normal">@/Components/SwipeableTabs.vue</span>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                Navigasi tab geser layar sentuh (touch gesture swipe) untuk berpindah antrean penjemputan atau status manifest dengan transisi halus ala aplikasi native.
                            </p>
                        </div>

                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/60 space-y-2">
                            <div class="flex items-center gap-2 font-mono text-xs font-bold text-dark">
                                <span class="bg-blue-800 text-white px-2 py-0.5 rounded">useCameraManager.js</span>
                                <span class="text-gray-400 font-normal">@/Composables/useCameraManager.js</span>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                Composable terpusat untuk mengelola izin kamera, enumerasi perangkat video HP, penggantian kamera depan/belakang, dan pencegahan bentrok MediaStream.
                            </p>
                        </div>

                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/60 space-y-2">
                            <div class="flex items-center gap-2 font-mono text-xs font-bold text-dark">
                                <span class="bg-blue-800 text-white px-2 py-0.5 rounded">useLocationTracking.js</span>
                                <span class="text-gray-400 font-normal">@/Composables/useLocationTracking.js</span>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                Composable background watchPosition untuk memantau pergerakan supir dan mengirimkan koordinat ke server berdasarkan interval dan batas minimum jarak (meters).
                            </p>
                        </div>

                    </div>
                </div>

            </div>

            <!-- ========================================================================= -->
            <!-- TAB 3: RIWAYAT PERKEMBANGAN & PERUBAHAN (CHANGELOG) -->
            <!-- ========================================================================= -->
            <div v-if="activeMainTab === 'changelog'" class="space-y-8">
                
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/60 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-amber-600 uppercase tracking-wider">Perkembangan & Rilis</span>
                            <h3 class="font-black text-lg text-dark flex items-center gap-2 mt-0.5">
                                <i class="ri-history-line text-amber-600"></i> Rekam Jejak Pembaruan Sistem (Changelog)
                            </h3>
                        </div>
                        <span class="text-xs font-mono bg-gray-100 px-2.5 py-1 rounded-lg text-gray-600 font-semibold">
                            File Sumber: CHANGELOG.md
                        </span>
                    </div>

                    <div class="p-6 sm:p-8 space-y-8">
                        <div class="relative pl-6 sm:pl-8 border-l-2 border-amber-200 space-y-10">

                            <!-- Release v2.5.0 -->
                            <div class="relative">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs ring-4 ring-blue-50 shadow-sm">
                                    <i class="ri-global-line text-base"></i>
                                </div>
                                <div class="bg-blue-50/50 rounded-2xl p-6 border border-blue-200/80 space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="bg-blue-600 text-white font-mono text-xs font-bold px-2.5 py-1 rounded-lg">v2.5.0</span>
                                            <span class="font-bold text-base text-dark">Go-Live Produksi & Perencanaan Aplikasi Mobile Lapangan</span>
                                        </div>
                                        <span class="text-xs text-blue-600 font-semibold bg-blue-100/70 px-2 py-0.5 rounded-full">21 Oktober 2026</span>
                                    </div>
                                    <ul class="text-xs text-gray-700 space-y-2 list-disc list-inside leading-relaxed">
                                        <li><strong>Hosting Produksi Live</strong>: Aplikasi resmi beroperasi di <code class="bg-blue-100/80 px-1 py-0.5 rounded font-mono text-blue-800">https://cargo.juraganfurniture.com</code>, database terintegrasi SQLite, migrasi skema tabel, dan inisialisasi akun login default.</li>
                                        <li><strong>Roadmap 3 Modul Lapangan</strong>: Pemetaan operasional untuk <strong>Supir Pickup</strong> (jemput mebel pengrajin & offline mode), <strong>Petugas Gudang</strong> (inbound checker koli & audit fisik), dan <strong>Supir Lintas</strong> (surat jalan digital, rute dinamis, & rekonsiliasi COD).</li>
                                        <li><strong>Penyelarasan Role & Auto-Redirect</strong>: Perancangan login cerdas agar supir dan petugas gudang langsung diarahkan ke layar kerja masing-masing tanpa tersesat di dashboard kantor.</li>
                                        <li><strong>Arsitektur Packaging APK Capacitor</strong>: Penyelarasan target server URL ke domain produksi, integrasi scanner kamera MLKit, background GPS tracking, dan deep link <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">jeparacargo://</code>.</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Release v2.4.0 -->
                            <div class="relative">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-emerald-50 shadow-sm">
                                    <i class="ri-check-line text-base"></i>
                                </div>
                                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200/80 space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="bg-emerald-600 text-white font-mono text-xs font-bold px-2.5 py-1 rounded-lg">v2.4.0</span>
                                            <span class="font-bold text-base text-dark">Pembaruan Dokumentasi Terpadu & Pembersihan Workspace</span>
                                        </div>
                                        <span class="text-xs text-gray-500 font-medium">21 Oktober 2026</span>
                                    </div>
                                    <ul class="text-xs text-gray-700 space-y-2 list-disc list-inside leading-relaxed">
                                        <li><strong>Dokumentasi Terpadu</strong>: Menghadirkan sistem tab interaktif (Panduan Pengguna, Developer & Teknis, dan Riwayat Perkembangan) dengan pemetaan 7 alur kerja end-to-end mebel kargo.</li>
                                        <li><strong>Strategi Distribusi Dual-Platform</strong>: Perumusan arsitektur resmi pembagian perangkat — Web Browser/PWA untuk Dashboard, Kasir, & Cetak Resi vs Android APK Native untuk Supir (Background GPS & Offline) dan Petugas Checker (Kamera MLKit & WakeLock).</li>
                                        <li><strong>Pembersihan Workspace</strong>: Menghapus build cache mobile flutter (<code class="bg-gray-200 px-1 py-0.5 rounded font-mono">mobile/build/</code> ~2.3 GB), file dump sementara (<code class="bg-gray-200 px-1 py-0.5 rounded font-mono">wilayah_head.json</code>, <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">emsifa.zip</code>, <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">cookie.txt</code>, dan folder <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">scratch/</code>).</li>
                                        <li><strong>Log & Cache Reset</strong>: Mengosongkan file log besar dan mengoptimalkan framework via <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">php artisan optimize:clear</code>.</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Release v2.3.0 -->
                            <div class="relative">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-blue-50 shadow-sm">
                                    <i class="ri-truck-line text-base"></i>
                                </div>
                                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200/80 space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="bg-blue-600 text-white font-mono text-xs font-bold px-2.5 py-1 rounded-lg">v2.3.0</span>
                                            <span class="font-bold text-base text-dark">Peningkatan UX Lapangan & Checker Samping Bak Truk</span>
                                        </div>
                                        <span class="text-xs text-gray-500 font-medium">13 Oktober 2026</span>
                                    </div>
                                    <ul class="text-xs text-gray-700 space-y-2 list-disc list-inside leading-relaxed">
                                        <li><strong>Safety Button Manifest</strong>: Mengamankan tombol simpan manifest agar tidak rawan tertekan oleh petugas checker saat di samping truk.</li>
                                        <li><strong>Input Teks vs Scanner Bergantian</strong>: Form teks otomatis menyembunyikan tombol scan saat kolom teks ada isinya (dan sebaliknya) untuk efisiensi ruang layar.</li>
                                        <li><strong>Counter Jumlah Resi</strong>: Indikator penghitung real-time jumlah nomor resi yang telah termuat ke manifest truk.</li>
                                        <li><strong>Inbound Gudang (<code class="bg-gray-200 px-1 py-0.5 rounded font-mono">Inbound.vue</code>)</strong>: Pemindaian barcode koli yang turun dari mobil supir jemput, otomatis update status resi menjadi <em>Di Gudang</em> atau <em>Sebagian di Gudang</em>.</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Release v2.2.0 -->
                            <div class="relative">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-purple-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-purple-50 shadow-sm">
                                    <i class="ri-map-pin-line text-base"></i>
                                </div>
                                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200/80 space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="bg-purple-600 text-white font-mono text-xs font-bold px-2.5 py-1 rounded-lg">v2.2.0</span>
                                            <span class="font-bold text-base text-dark">Supir Magic Link & Live Telemetri GPS Lokasi</span>
                                        </div>
                                        <span class="text-xs text-gray-500 font-medium">11 Oktober 2026</span>
                                    </div>
                                    <ul class="text-xs text-gray-700 space-y-2 list-disc list-inside leading-relaxed">
                                        <li><strong>Magic Link Tanpa Password</strong>: Supir penjemputan dan supir lintas mengakses lembar kerja lewat token acak 48 karakter via tautan WhatsApp.</li>
                                        <li><strong>Background Live GPS</strong>: Composable <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">useLocationTracking.js</code> mengirimkan koordinat supir ke server secara berkala untuk dipantau di radar <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">/lokasi-supir</code>.</li>
                                        <li><strong>Pelacakan Publik Transparan</strong>: Endpoint <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">/track-resi/{resi}</code> untuk cek resi mandiri oleh konsumen mebel.</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Release v2.1.0 -->
                            <div class="relative">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-amber-500 text-white flex items-center justify-center font-bold text-xs ring-4 ring-amber-50 shadow-sm">
                                    <i class="ri-qr-scan-line text-base"></i>
                                </div>
                                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200/80 space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="bg-amber-600 text-white font-mono text-xs font-bold px-2.5 py-1 rounded-lg">v2.1.0</span>
                                            <span class="font-bold text-base text-dark">Komponen Barcode Scanner & Master Wilayah EMSIFA</span>
                                        </div>
                                        <span class="text-xs text-gray-500 font-medium">10 Oktober 2026</span>
                                    </div>
                                    <ul class="text-xs text-gray-700 space-y-2 list-disc list-inside leading-relaxed">
                                        <li><strong>Komponen <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">&lt;BarcodeScanner /&gt;</code></strong>: Engine pemindai barcode kamera HP dengan audio beep dan saklar lampu senter.</li>
                                        <li><strong>Integrasi Wilayah Indonesia</strong>: Sinkronisasi database lokal berjenjang provinsi, kabupaten, kecamatan, desa dengan sistem self-healing.</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Release v2.0.0 & v1.0.0 -->
                            <div class="relative">
                                <div class="absolute -left-[35px] sm:-left-[43px] top-0 w-8 h-8 rounded-full bg-slate-600 text-white flex items-center justify-center font-bold text-xs ring-4 ring-slate-100 shadow-sm">
                                    <i class="ri-rocket-line text-base"></i>
                                </div>
                                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200/80 space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="bg-slate-700 text-white font-mono text-xs font-bold px-2.5 py-1 rounded-lg">v1.0.0 - v2.0.0</span>
                                            <span class="font-bold text-base text-dark">Multi-Tenant Agregator & Fondasi Mobile-First</span>
                                        </div>
                                        <span class="text-xs text-gray-500 font-medium">4–8 Oktober 2026</span>
                                    </div>
                                    <ul class="text-xs text-gray-700 space-y-2 list-disc list-inside leading-relaxed">
                                        <li><strong>Multi-Tenant Architecture</strong>: Skema model <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">Expedition</code> untuk menampung banyak cabang dan mitra ekspedisi.</li>
                                        <li><strong>Fondasi Mobile & PWA</strong>: Auto-hide header saat scroll, gesture sentuh <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">&lt;SwipeableTabs /&gt;</code>, dan panel adaptif <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">&lt;AppDrawer /&gt;</code>.</li>
                                    </ul>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer note -->
            <div class="text-center py-6 border-t border-gray-100">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-bold">
                    Jepara Cargo Logistics Engine &bull; Dokumentasi Diperbarui 2026
                </p>
            </div>

        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

// State untuk Tab Utama: 'user' (Panduan Pengguna / SOP) atau 'dev' (Developer & Teknis)
const activeMainTab = ref('user');

// State untuk Pencarian Cepat dalam Dokumentasi
const searchQuery = ref('');
</script>
