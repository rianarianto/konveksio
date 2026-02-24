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
2. **Approval Desain**: Designer masuk ke `DesignTaskResource`, mengunduh *brief*, dan menggunggah gambar final. Setelah disimpan, *Item-Level design_status* otomatis berubah jadi `approved`.
   *(Jika seluruh item dalam order telah 'approved', Status Induk Order akan otomatis naik kelas dari `diterima` menjadi `antrian`).*
3. **Kontrol Produksi (Grouped Table UI)**: Pemantauan kerja dipusatkan di halaman `ManageControlProduksis`. Base model diubah ke `OrderItem::class` agar data dapat dikelompokkan secara native oleh Filament menggunakan `->defaultGroup(TableGroup::make('order.order_number'))`. Tabel menampilkan kolom per produk (Nama, Qty, Kategori, Deadline, Status Produksi) dengan setiap grup bisa di-*collapse*. Widget ProduksiStats tetap di atas sebagai ringkasan. Penugasan tugas dilakukan via *Page Action* slideOver.
4. **Tahapan Produksi (Relay/Estafet)**: Admin mendelegasikan pengerjaan melalui tabel berelasi `production_tasks`. Master data *stage* (Potong->Jahit->Kancing->QC) ditentukan oleh tabel `production_stages`. Admin tidak bisa menugaskan *quantity* pada *Stage B* melebihi jumlah celcius *Stage A* yang sudah dikerjakan (*Validasi Estafet*).
5. **Penyelesaian**: Status naik bertahap menjadi `diproses` -> `selesai` -> `siap_diambil`.

---

## 5. Struktur Database Utama

- **`shops`**: Data profil identitas Toko Induk.
- **`users`**: Karyawan sistem yang memiliki Hak Akses. (Relasi *BelongsTo* -> `shops`)
- **`customers`**: Database Pelanggan.
- **`customer_measurements`**: Penyimpanan spesifikasi detail ukuran badan tiap anggota.
- **`orders`**: Dokumen/faktur utama ("Keranjang"). Memuat ringkasan *total_price*, *down_payment*, dan status makro (`diterima`, `antrian`, dll). 
- **`order_items`**: Rincian sub-pesanan di dalam sebuah faktur. Memuat *quantity*, informasi *JSON size_and_request_details*, kategori barang, aset gambar, dan riwayat desain.
- **`production_stages`**: Urutan cetakan pabrik / rantai perizinan. Dikelompokkan apakah khusus untuk baju *Custom*, untuk *Non-Produksi*, dst.
- **`production_tasks`**: Rincian riwayat operasi pabrik per produk yang didelegasikan oleh *Admin* kepada *User/Karyawan* bersangkutan. Bisa memuat deskripsi, kuantitas target, status kerja, rekap `size_quantities` untuk pelacakan spesifik ukuran yang diolah.

---

*File ini adalah pusat sumber panduan awal (Cheat Sheet). Silakan perbarui file ini jika di kemudian hari dilakukan pemecahan monolitik menjadi microservices atau refaktor besar-besaran.*
