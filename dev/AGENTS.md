# WEO ? instruksi aplikasi

Ikuti `../AGENTS.md` sebagai konvensi utama. Seluruh komunikasi, UI, dan dokumentasi menggunakan bahasa Indonesia. Laravel 13 + Inertia 2 + Vue 3 + TypeScript, MariaDB, dan penyimpanan privat.

Jalankan dari folder `dev`:

- `php artisan test --compact` ? hanya database `weo_test`, akun `weo_test`; pengaman bekerja sebelum RefreshDatabase.
- `npm run typecheck`
- `npm run format:check`
- `npm run build`
- `php vendor/bin/pint --test`
- `npm run test:browser` ? membutuhkan server lokal dan Microsoft Edge; membuat proyek uji lalu mengarsipkannya.

Untuk lingkungan ini PHP portable: `../.tools/php/php.exe`; Composer: `../.tools/php/php.exe ../.tools/composer.phar`. Runtime dan credential lokal diabaikan Git. Gunakan lockfile; jangan scaffold ulang, migrate:fresh di database aplikasi, atau mengunduh ulang runtime saat tersedia.

Policy proyek dipakai pada seluruh route, ID relasi wajib dicakup proyek. Status approval dan pembayaran diubah melalui service dan transaksi/locking. Dokumen dan PDF tidak menerima URL/file arbitrer. Jangan memasang Boost atau alat tambahan tanpa kebutuhan konkret.

Uji konkurensi: `php artisan test --filter=ConcurrentTransactionsTest --compact`. Uji dump/restore: `php artisan test --filter=BackupRestoreTest --compact`; klien MariaDB CLI harus tersedia. Keduanya hanya boleh menggunakan weo_test. Jangan memindahkan pembacaan saldo verifikasi menjadi consistent read tanpa lock; regression test dua proses harus tetap lulus.
