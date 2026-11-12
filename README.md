# WEO — Wedding Organizer Workspace

Aplikasi lokal satu perusahaan Wedding Organizer: workspace internal dan portal pasangan dari database yang sama. Laravel 13.37, PHP 8.4, MariaDB 11.4, Vue 3, TypeScript, Inertia 2, Tailwind 4, Vite 8. Tidak memerlukan layanan berbayar.

## Mulai pada komputer ini

Runtime portable sudah tersedia dalam `.tools` (diabaikan Git). Database aplikasi `weo`, database test `weo_test`, port MariaDB `3307`. Credential dibuat acak dalam konfigurasi lokal dan tidak dicetak.

```powershell
cd F:\project\weo
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\windows\Start-WEO.ps1
```

Buka **http://127.0.0.1:8000**. Login: **http://127.0.0.1:8000/login**.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\windows\Stop-WEO.ps1
```

Script memeriksa PID dan executable milik proyek sebelum menghentikan proses. Tidak memasang service atau mengubah PATH/execution policy global. Tambahkan `-Scheduler` pada Start-WEO untuk pengingat otomatis. Tanpa itu, jalankan pengingat manual atau `schedule:work` di terminal sendiri.

## Akun demo development

Semua akun demo memakai kata sandi **`WeoDemo!2026#`**. Identitas seluruhnya fiktif, khusus lingkungan lokal.

| Role | Email |
| --- | --- |
| Admin | `admin@weo.test` |
| Admin kedua | `admin2@weo.test` |
| PIC 1 / 2 | `pic1@weo.test`, `pic2@weo.test` |
| Tim 1–3 | `tim1@weo.test`, `tim2@weo.test`, `tim3@weo.test` |
| Pasangan proyek 1 | `bride1@weo.test`, `groom1@weo.test` |
| Pasangan proyek 2 | `bride2@weo.test`, `groom2@weo.test` |
| Pasangan proyek 3 | `bride3@weo.test`, `groom3@weo.test` |

Seeder menyediakan 3 proyek dan 8 vendor, checklist, tugas, meeting, dokumen privat, rundown, persetujuan kedua pasangan, pembayaran sebagian, serta bukti menunggu verifikasi. Proyek pengujian browser ditandai `Uji Browser …` dan diarsipkan setelah pengujian berhasil.

## Fungsi

- Dashboard lintas proyek dan ringkasan proyek; angka dihitung dari data aktual.
- Checklist wajib/berlaku, template snapshot, tugas/PIC/target/hambatan; Next Action memakai data tugas yang sama.
- Approval draft dan revisi berversi, dokumen/rundown tertentu, aturan salah satu/kedua pasangan, komentar, keputusan dan audit.
- Master vendor dan penugasan proyek, booking, kontrak melalui dokumen privat, kesepakatan dan catatan internal.
- Tagihan klien dan kewajiban vendor menggunakan tabel serta endpoint terpisah; pembayaran manual, bukti, verifikasi, pembatalan beralasan.
- Dokumen privat dengan versi, kategori, pencarian, lampiran tugas/meeting/kontrak vendor; download melalui izin backend.
- Meeting, agenda, peserta, pembahasan, keputusan dan tindak lanjut sebagai tugas terhubung.
- Rundown tanggal/waktu, lintas tengah malam, jalur paralel, edit/hapus draft, urutan naik/turun, versi final/revisi, PDF, pengajuan approval.
- Notifikasi dibaca/belum dibaca; scheduler pengingat idempotent.
- Laporan filter periode tenggat dan PDF sesuai role.
- PWA dasar: manifest, ikon SVG, halaman offline; cache hanya ikon dan halaman offline, tanpa data privat.
- Akun terkontrol, penugasan anggota, arsip proyek, activity log, session/CSRF/rate limit dan policy.

## Perintah aplikasi

Jalankan semua perintah berikut dari `F:\project\weo\dev`. Jika PHP/Composer sudah terpasang pada PATH, gunakan `php`/`composer`. Untuk runtime portable komputer ini:

```powershell
cd F:\project\weo\dev
..\.tools\php\php.exe ..\.tools\composer.phar install
npm.cmd ci
..\.tools\php\php.exe artisan migrate
..\.tools\php\php.exe artisan db:seed
npm.cmd run typecheck
npm.cmd run build
..\.tools\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

`.env` telah dikonfigurasi lokal; jangan menimpanya. Pada komputer baru, salin `.env.example`, buat database/user sendiri, isi credential, lalu `php artisan key:generate`. Tidak menjalankan `storage:link`: berkas proyek tetap privat.

```powershell
..\.tools\php\php.exe artisan test --compact
..\.tools\php\php.exe vendor/bin/pint --test
npm.cmd run typecheck
npm.cmd run format:check
npm.cmd run build
npm.cmd run test:browser
..\.tools\php\php.exe artisan weo:remind
..\.tools\php\php.exe artisan schedule:work
```

Backend test memerlukan `.env.testing` dengan akun/database `weo_test`, terpisah dari aplikasi. Pemeriksaan keselamatan bekerja sebelum trait RefreshDatabase. Browser test memakai Microsoft Edge yang sudah terpasang dan server lokal aktif, membuat data fiktif melalui UI, lalu mengarsipkan proyek hasil uji. Untuk pengembangan frontend interaktif: `npm.cmd run dev` di terminal tambahan, bersama server Laravel.

## Panduan

- [Setup Windows](docs/guides/setup-windows.md)
- [Panduan pengguna](docs/guides/user-guide.md)
- [Backup dan restore](docs/guides/backup-restore.md)
- [Lingkup dan asumsi](docs/requirements/mvp-scope.md)
- [Matriks izin](docs/requirements/role-permission-matrix.md)
- [Arsitektur](docs/architecture/system-overview.md) dan [ERD](docs/database/erd.md)
- [Hasil penerimaan](docs/testing/acceptance-checklist.md)
- [Catatan pengembangan](docs/progress/development-log.md)

Pengembangan dan verifikasi bersifat lokal. Deployment production, integrasi eksternal, payment gateway, portal vendor, margin/budget dan command center tidak termasuk tahap ini. Uji dump/restore SQL dan berkas privat telah lulus pada database test terpisah. Uji beban, pemulihan dataset operasional, operasi production dan instalasi PWA lintas perangkat perlu verifikasi tersendiri.

## Pemeriksaan lanjutan 18 Oktober 2026

Hasil terbaru: **39 test backend, 360 assertion**, termasuk empat uji dua proses serentak dan satu siklus dump/restore SQL serta berkas privat. Pengujian browser mencakup **18 pemeriksaan** tanpa error JavaScript. Lihat [hasil penerimaan terbaru](docs/testing/acceptance-checklist.md).

Perintah terarah dari `dev`:

```powershell
..\.tools\php\php.exe artisan test --filter=ConcurrentTransactionsTest --compact
..\.tools\php\php.exe artisan test --filter=BackupRestoreTest --compact
```

Uji restore memerlukan klien `mariadb-dump` dan `mariadb` yang tersedia pada runtime portable atau PATH. Seluruh pengosongan dan restore dilakukan pada `weo_test` dan storage fake; database aplikasi tidak disentuh. Password proses disuplai melalui environment, tanpa ditulis ke argumen/dump/laporan.

Script Composer bawaan `setup` yang mengganti APP_KEY dan memaksa migrasi telah dihapus. Ikuti langkah setup eksplisit pada panduan; `composer dev` hanya menjalankan server lokal, sedangkan Vite interaktif dijalankan terpisah dengan `npm.cmd run dev`.

## Verifikasi runtime 5 November 2026

Akses Edge dengan build dan Vite telah diuji: 39 test backend/360 assertion serta 18 pemeriksaan browser lulus. Start-WEO kini membersihkan penanda Vite loopback yang usang saat port tidak aktif. [Laporan perubahan dan bukti pengujian](docs/testing/2026-11-05-runtime-browser-check.md). Aplikasi, Vite, dan database ditutup setelah pengujian terakhir.
