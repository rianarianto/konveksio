# Konveksio - System Architecture & Guide

Buku panduan ini merangkum seluruh struktur sistem, alur database, dan aturan kerja (*business logic*) pada aplikasi Konveksio untuk mempermudah pemahaman atau *onboarding* *developer* baru terhadap kode ini.

---

## 1. Stack Teknologi
- **Framework**: Laravel 12
- **Admin Panel**: Filament v3
- **CSS Framework**: Tailwind CSS v4 (via Vite)
- **Database**: MySQL

---

## 2. Multi-Tenancy (Konsep Toko / Cabang)
Aplikasi ini mendukung *Multi-Outlet* (banyak toko). 
Setiap entitas data utama **wajib** memiliki `shop_id` dan diisolasi dengan Global Scope (`App\Models\Scopes\ShopScope`).
- **Owner**: Bisa melihat semua toko (bisa *bypass* fungsi multi-tenancy).
- **Admin & Designer**: Hanya bisa melihat data pesanan dari toko tempat mereka ditugaskan.
- **Tenant URL**: Format URL panel admin menggunakan `http://konveksio.test/app/{shop_id}`.

### Aturan Relasi Tenant pada Model
Setiap model yang berelasi dengan Toko wajib menggunakan *trait* tertentu:
- `App\Models\User`: Menggunakan fungsi natively dari standar Filament `HasTenants`.
- `App\Models\Order`, `App\Models\Customer`, `App\Models\ProductionTask`: Menggunakan `ShopScope` lewat method `booted()`.
- `App\Models\OrderItem`: Secara tidak langsung berelasi ke Toko via induknya (`Order`). Relasi *multi-tenancy*-nya dinamakan `orderShop()` menggunakan `HasOneThrough`.

---

## 3. Role & Otoritas Berbasis Jabatan
Aplikasi hanya memiliki satu panel utama (`/app`), namun hak akses komponen (Resource/Page) disaring berdasarkan properti `role` dari tabel `users`:
1. **Owner (`owner`)**: Bebas mengakses semua Resource, termasuk `ShopResource` (Manajemen Toko) dan mengatur karyawan (`UserResource`).
2. **Admin (`admin`)**: Mengendalikan form order (`OrderResource`) dan Panel Kontrol Produksi. Tidak Punya Akses konfigurasi toko mapun karyawan.
3. **Designer (`designer`)**: Hanya bisa mengakses `DesignTaskResource` untuk mengunggah aset visual. Tidak diperkenankan menghapus atau membuat pesanan baru.

---

## 4. Alur Bisnis (Pesanan & Produksi)

### A. Kategori Pesanan (`production_category`)
Saat Admin menginput sebuah item pesanan (`OrderItem`), sistem membaginya ke dalam 4 kategori dengan perlakuan form dan kalkulasi harga yang berbeda (dikelola lewat format dinamis berbasis JSON di kolom `size_and_request_details`):
1. **Produksi (Size Toko)**: Memakai pola ukuran generik toko (S, M, L, XL). Memiliki form *repeater* variasi ukuran yang dikonversi dari baris ke nilai *total quantity*.
2. **Produksi Custom (Ukur Badan)**: Menyimpan ukuran badan spesifik tiap orang (LD, LP, PB, dll). Otomatis tersimpan ke tabel referensi `customer_measurements` ketika *Order* dibuat.
3. **Non-Produksi (Barang Jadi Supplier)**: Beli putus. Kalkulasi harga berbasis *harga supplier* + biaya opsi tambahan (Sablon/Bordir).
4. **Jasa**: Hanya murni jasa (Misal: permak atau jasa sablon baju bawaan pelanggan). Kalkulasi datar jumlah dikali harga satuan.

### B. Alur Workflow (Desain -> Produksi)
Sistem memiliki urutan *state machine* (*status tracker*) antara model *Order* dan *OrderItem*:

1. **Pembuatan Pesanan**: Admin input di form `OrderResource`. Status Pesanan: `diterima`. Status Desain (*Item-Level*): `pending`.

---

## 5. Panduan Pengembangan Berbasis AI (AI Guidelines)

Bagian ini ditujukan bagi asisten AI (dan *developer*) untuk memahami batasan lingkungan pengembangan saat ini guna menghindari kerusakan tampilan (*UI breakage*).

### A. Kendala Build CSS (Vite/Tailwind)
**PENTING**: Lingkungan pengembangan saat ini memiliki batasan keamanan (Execution Policy) yang mencegah eksekusi perintah `npm run build` atau `npm run dev`.
- **Dampak**: Menambahkan *class* Tailwind baru yang belum pernah digunakan sebelumnya tidak akan muncul di browser karena file CSS final tidak bisa diperbarui secara otomatis.
- **Instruksi untuk AI**: 
  - Gunakan **Inline Styles** (Atribut `style="..."`) untuk dekorasi layout dan elemen kustom pada file `.blade.php`.
  - Jangan mengandalkan *class* Tailwind baru untuk perubahan UI yang sifatnya mendesak atau krusial.

### B. Styling & Konsistensi UI
- **Warna Dasar**: Gunakan kode Hex atau warna standar Tailwind yang sudah ada (misal: `#7F00FF` untuk ungu, `#666666` untuk teks abu-abu gelap).
- **Badge & Status**: Pastikan badge status (seperti Express, Aktif, Antrian) memiliki desain yang konsisten (teks tebal, warna latar kontras rendah, teks kontras tinggi).

### C. Multi-Tenancy
- Selalu gunakan `shop_id` untuk setiap query data utama demi keamanan data antar toko.
- Gunakan `\Filament\Facades\Filament::getTenant()->id` untuk mengambil ID toko yang sedang aktif. 
2. **Approval Desain**: Designer masuk ke `DesignTaskResource`, mengunduh *brief*, dan menggunggah gambar final. Setelah disimpan, *Item-Level design_status* otomatis berubah jadi `approved`.
   *(Jika seluruh item dalam order telah 'approved', Status Induk Order akan otomatis naik kelas dari `diterima` menjadi `antrian`).*
3. **Kontrol Produksi (Grouped Table UI)**: Pemantauan kerja dipusatkan di halaman `ManageControlProduksis`. Base model diubah ke `OrderItem::class` agar data dapat dikelompokkan secara native oleh Filament menggunakan `->defaultGroup(TableGroup::make('order.order_number'))`. Tabel menampilkan kolom per produk (Nama, Qty, Kategori, Deadline, Status Produksi) dengan setiap grup bisa di-*collapse*. Widget ProduksiStats tetap di atas sebagai ringkasan.
   - **Modal Atur Tugas / Detail**: Saat baris pesanan diklik, sebuah slide-over terbuka memuat "Spesifikasi Produksi" lengkap. Field JSON (`size_and_request_details`) diurai/diparsing untuk merender data kompleks (Ukuran custom pelanggan dilipat/collapsible, material bahan dari `supplier_product` jika pesanan non-produksi, serta *Tambahan Request* diformat bebas dari JSON key). Terdapat status-bar *progress* persentase berjenjang.
4. **Tahapan Produksi (Relay/Estafet)**: Admin mendelegasikan pengerjaan melalui tabel berelasi `production_tasks`. Master data *stage* (Potong->Jahit->Kancing->QC) ditentukan oleh tabel `production_stages`. Tombol Aksi di header akan menyesuaikan persentase dan warna progres (Kerjakan, Selesai, Batal). Admin tidak bisa menugaskan *quantity* pada *Stage B* melebihi jumlah celcius *Stage A* yang sudah dikerjakan (*Validasi Estafet*).
5. **Penyelesaian**: Status pesanan dan status produksi naik bertahap menjadi `diproses` -> `selesai` -> `siap_diambil`.
56: 6. **Keuangan & Pelunasan**: Pembayaran dikelola di `KeuanganResource`. Mendukung pembayaran bertahap (multi-payment). Order dengan sisa tagihan 0 akan otomatis berstatus lunas di Ringkasan.
57: 

---

## 5. Struktur Database Utama
- **`users`**: Karyawan sistem yang memiliki Hak Akses. (Relasi *BelongsTo* -> `shops`)
- **`customers`**: Database Pelanggan.
- **`customer_measurements`**: Penyimpanan spesifikasi detail ukuran badan tiap anggota.
- **`orders`**: Dokumen/faktur utama ("Keranjang"). Memuat ringkasan *total_price* dan status makro (`diterima`, `antrian`, dll).
- **`order_items`**: Rincian sub-pesanan. Memuat *quantity*, JSON `size_and_request_details`, kategori, aset gambar, dan riwayat desain.
- **`production_stages`**: Master urutan tahapan pabrik (Potong → Jahit → QC).
- **`production_tasks`**: Rincian penugasan produksi per item, termasuk `stage_name`, `assigned_to` (→ `workers`), `wage_amount`, `quantity`, `status`, dan `completed_at`.
- **`payments`**: Catatan transaksi pembayaran pelanggan multi-cicilan (DP, Cicilan, Pelunasan). Memuat `proof_image`, `payment_method`, `recorded_by`.
- **`workers`**: Master data pekerja produksi (Tukang) untuk perhitungan upah borongan.
- **`expenses`**: Pencatatan pengeluaran operasional toko.

---

## 6. Dashboard Widget Architecture

Dashboard admin menggunakan **2 baris widget** dengan data real dinamis per tenant:

### Baris 1 — `AktivitasUtamaWidget` (`$sort = 1`)
- 4 kartu statistik: Pesanan Masuk, Diproses, Siap Di-ambil, Deadline Hari Ini.
- Trend persentase dihitung live vs kemarin.
- Desain menggunakan **native CSS** (bukan Tailwind arbitrary values) agar style di-render benar.

### Baris 2 — `DashboardRow2Widget` (`$sort = 3`)
- **Panel Kiri (Arus Kas)**: Total Piutang + Pemasukan Hari Ini + SVG Donut Chart dinamis.
  - Donut chart: `viewBox "0 0 140 140"` dengan center `(70,70)` dan `r=54` sehingga stroke tidak terpotong.
- **Panel Kanan (Aktivitas Terbaru)**: 5 `ProductionTask` terbaru diubah, badge stage bewarna per jenis.

### Registrasi Widget (`AdminPanelProvider`)
- Widget didaftarkan **secara eksplisit** (bukan `discoverWidgets()`).
- Widget default Filament (`AccountWidget`, `FilamentInfoWidget`) **dihapus**.

---

*File ini adalah pusat sumber panduan awal (Cheat Sheet). Silakan perbarui file ini jika di kemudian hari dilakukan pemecahan monolitik menjadi microservices atau refaktor besar-besaran.*

## Owner Finance Widgets & Shop Unification
### Widget Architecture
- **OwnerFinanceStatsWidget**: Menggunakan height: 100% dan lex-direction: column untuk layout yang seimbang.
- **Piutang Macet Logic**: Menggunakan logika tren terbalik (Warna Danger jika nilai Piutang hari ini > dari bulan lalu).
- **Workshop Load Logic**: Dihitung dari Total Pcs Aktif (sum of quantity order items dengan status dikerjakan) berbanding dengan max_capacity_pcs milik tenant (Shop).

### Multi-tenancy & Shop Settings
- **Shop Settings Centralization**: Fitur 	enantProfile bawaan Filament dimatikan untuk menghindari halaman ganda. Pengaturan toko kini dipusatkan di ShopResource.
- **User Menu Extension**: Menambahkan menu "Shop Settings" secara dinamis di pojok kanan atas yang langsung mengarah ke halaman edit toko yang sedang aktif.

### Order Action Refactoring (View-Based)
- **Custom Action Dropdown**: Memindahkan logika HTML/Alpine.js aksi tabel (View, Receipt, Edit, Delete) ke file blade terpisah `resources/views/filament/resources/orders/actions.blade.php`.
- **Row Interaction Control**: Menggunakan `->recordUrl(null)` dan `->recordAction(null)` pada `OrderResource` untuk mencegah klik baris memicu navigasi, sehingga interaksi terfokus pada tombol aksi dropdown.
- **CSS Align-Top**: Menambahkan `vertical-align: top` secara global di `AdminPanelProvider` dan padding khusus pada kolom aksi agar sejajar dengan teks nama produk yang berbaris banyak.

### Multiple Payments Architecture
- **Payment Record Migration**: Sistem beralih dari satu kolom `down_payment` ke tabel `payments`. Setiap pesanan (`orders`) dapat memiliki banyak catatan pembayaran (`payments`).
- **Dynamic Balance Calculation**: Saldo sisa (`remaining_balance`) dihitung secara dinamis di level model dengan menjumlahkan seluruh nilai `amount` pada tabel `payments` dan menguranginya dari `total_price`.
- **Legacy Compatibility**: Kolom `down_payment` dipertahankan sebagai backup namun tidak lagi digunakan di UI atau filter pencarian.

### Order Return & Damage Tracking
- **OrderReturn Component**: Modul baru untuk mencatat barang rusak atau retur yang terhubung langsung ke `Order`.
- **Integrated UX Actions**: Fitur retur tidak lagi diletakkan di sidebar, melainkan diintegrasikan sebagai *Table Action* di daftar pesanan dan *Header Action* di halaman edit, untuk alur kerja yang lebih intuitif bagi operator.

---
*Terakhir Diperbarui: 8 Maret 2026*

### 10 Maret 2026 — Keuangan Unification & Production UI Fixes

#### ✅ Keuangan & Pengeluaran Consolidation
- **Unifikasi Menu**: Menyederhanakan navigasi keuangan menjadi dua menu utama: **Kas Masuk & Piutang** dan **Kas Keluar / Pengeluaran**.
- **Refaktor Resource**: Memindahkan fitur Pengeluaran (Expenses) ke dalam `KeuanganResource` sebagai tab "Daftar Pengeluaran" untuk menghindari redundansi halaman.
- **Pembersihan**: Menghapus `PengeluaranResource` (halaman lama) guna merampingkan struktur aplikasi.
- **Widget Integration**: Mengintegrasikan statistik "Ringkasan Pengeluaran" langsung di atas tabel pengeluaran baru.

#### ✅ Production Task UI Improvements
- **Material Labeling**: Memperbaiki tampilan ID Material/Produk menjadi Nama asli yang mudah dibaca pada modal "Atur Tugas".
- **Visual Polish**: Menghapus badge biru (*hardcoded cyan*) dan menggantinya dengan desain yang lebih netral dan premium sesuai tema sistem.
- **Detail Ukuran Lengkap**: Memastikan seluruh field ukuran custom (LD, LP, P, PL, LB, LPi, PB) tampil secara transparan dan tidak terpotong.
- **Validasi Qty Custom**: Memperbaiki logika penghitungan quantity pada item custom agar sesuai dengan jumlah nama yang diinput.

#### Files Modified / Created
| File | Status |
|---|---|
| app/Filament/Resources/Keuangans/KeuanganResource.php | MODIFIED |
| app/Filament/Resources/Keuangans/KasMasukResource.php | MODIFIED |
| app/Filament/Resources/ControlProduksis/ControlProduksiResource.php | MODIFIED |
| app/Filament/Resources/PengeluaranResource.php | DELETE |

---
*
