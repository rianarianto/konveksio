# Development Progress - Konveksio

Catatan progress pengembangan fitur aplikasi konveksio secara kronologis.

---

## [2026-06-13] Fitur: Atur Tugas Produksi (AturTugasProduksi)

### Konteks
Halaman `AturTugasProduksi` digunakan oleh admin/kepala produksi untuk mengatur pembagian tugas pengerjaan per tahap (potong, jahit, finishing, dll.) kepada karyawan/tukang.

---

### Yang Sudah Dikerjakan

#### 1. Struktur Halaman & Repeater
- Nested repeater: **Tahap Produksi** > **Daftar Pekerja & Kuantitas**
- Setiap tahap bisa ditambahkan multiple pekerja beserta kuantitas per ukuran yang dikerjakan
- Header tiap tahap berwarna berbeda sesuai jenis tahap (potong, jahit, dll.)
- Header menampilkan ringkasan: nama tahap, total qty, dan status QC

#### 2. Pembagian Tugas Otomatis (`Bagi Tugas Otomatis`)
- Tombol di header repeater workers, warna primary
- **Algoritma sequential** untuk order tipe sise toko:
  - Ukuran dibagikan secara berurutan (S -> M -> L -> XL)
  - Tukang 1 mengerjakan dari awal antrean ukuran hingga kuotanya terpenuhi
  - Tukang 2 melanjutkan dari sisa ukuran, dst.
  - Satu ukuran diusahakan tidak pecah ke banyak tukang (hanya terpecah jika qty satu ukuran > kuota per tukang)
- **Custom order**: semua item custom diberikan ke tukang terakhir, sehingga tukang 1 dst. tetap balance secara total qty
- Manual tetap bisa dilakukan - user bisa ubah nilai input setelah auto-distribute

#### 3. Toggle Wajib QC
- Label dipersingkat: "Wajib di QC"
- `inline(false)` -> toggle tampil di bawah label, tidak inline
- `->live()` -> status QC SKIP di header collapsible berubah real-time saat toggle diubah

#### 4. Worker Item Label
- Default label collapsible item worker: "Pilih Karyawan (0 Pcs)"
- Setelah karyawan dipilih: menampilkan nama karyawan + qty

#### 5. Total Qty Field
- Field `Total Qty` dijadikan disabled (tidak bisa diedit user)
- Tetap dikirim ke server via `->dehydrated()`

#### 6. UI & Styling
- Tombol `+ Tambah Pekerja` -> warna primary
- Header collapsible tahap: spacing & gap dirapikan, badge QC SKIP tidak terpotong (flex-shrink:0)
- Summary text di header pakai separator yang lebih rapi

---

### File Utama
- `app/Filament/Resources/ControlProduksis/Pages/AturTugasProduksi.php`

---

### Backlog / Rencana Selanjutnya
- [ ] Integrasi notifikasi WhatsApp bot ke tukang setelah tugas di-assign
- [ ] Monitor progres tugas per tukang di halaman produksi
- [ ] Export SPK (Surat Perintah Kerja) per tukang

---

Last updated: 2026-06-13
