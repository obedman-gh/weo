# Aplikasi WEO

Seluruh kode Laravel dan frontend berada di folder ini. Baca [README proyek](../README.md), [setup Windows](../docs/guides/setup-windows.md), dan [instruksi kerja](AGENTS.md).

Jalankan Composer/npm/Artisan dari direktori ini. Credential lokal, dependency dan upload privat tidak boleh dimasukkan Git.

## Cakupan Git

Repository hanya melacak berkas di `dev/`. Folder `.tools`, `docs`, `scripts`, `prompt`, `sounds`, serta berkas root tetap lokal. Aturan pembatasan berada pada `.gitignore` di root workspace, yang juga tidak disertakan dalam commit.

Pada workspace baru yang memakai susunan folder yang sama, buat `.gitignore` di root dengan isi berikut sebelum menambahkan berkas ke Git:

```gitignore
/*
!/dev/
```

Aturan di `dev/.gitignore` melindungi `.env` beserta variannya (kecuali `.env.example`), dependency, upload privat, cache, log, hasil pengujian, serta build frontend. Tambahkan perubahan dengan `git add -- dev` dari root dan periksa `git diff --cached --name-only` sebelum commit. Jangan memakai `git add -f` untuk berkas lokal yang diabaikan.
