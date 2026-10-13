# Safe Deployment & Code Quality Rules

## 1. Local Validation Before Commit/Push (Wajib)
Sebelum melakukan `git commit` dan `git push`, agen WAJIB menjalankan validasi di lokal:
- Cek syntax file PHP: `php -l <file_path>`
- Cek integritas rute: `php artisan route:list`
- Cek rute khusus tenant/scope: pastikan rute berada di dalam context tenant yang benar jika berinteraksi dengan session/layout tenant.

## 2. Emergency Rollback Command (Tombol Darurat Server)
Jika terjadi error 500 di server production setelah pull:
```bash
git reset --hard HEAD~1
php artisan optimize:clear
```
Perintah ini mengembalikan commit terakhir ke kondisi stabil sebelumnya dalam hitungan detik.

## 3. Deployment Guide (Maintenance Mode)
- **Update Standar (Tampilan / Logic harian)**: Cukup `git pull origin main && php artisan optimize:clear` (tanpa down).
- **Update Berat (Migrasi Database / Skema Data)**: Gunakan mode pemeliharaan singkat:
  ```bash
  php artisan down --retry=5
  git pull origin main
  php artisan migrate --force
  php artisan optimize:clear
  php artisan up
  ```

## 4. Komunikasi
- Jawaban harus selalu ringkas, padat, dan langsung ke solusi (on-point).
