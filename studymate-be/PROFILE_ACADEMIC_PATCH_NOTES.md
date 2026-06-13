# StudyMate Patch: Profil Akademik & Minat

Patch ini memperbaiki input akademik agar user tidak perlu mengetik kode mata kuliah secara manual.

## Yang diubah

1. **Frontend Profil Akademik**
   - `frontend/src/views/ProfileView.vue`
   - Program studi sekarang memakai dropdown.
   - Minat akademik memakai pilihan kode + label, contoh: `AI — Kecerdasan Buatan`.
   - Mata kuliah aktif memakai searchable scroll list.
   - Opsi mata kuliah tampil sebagai `KODE — Nama Mata Kuliah`, contoh: `IF3302 — Kecerdasan Buatan`.
   - User bisa memilih banyak mata kuliah dan menghapusnya lewat chip.

2. **Katalog akademik frontend**
   - `frontend/src/data/academicCatalog.js`
   - Berisi fallback program, mata kuliah, dan minat supaya dropdown tetap banyak walaupun database awal masih minim.

3. **Backend penyimpanan profil**
   - `backend-laravel/app/Http/Controllers/UserController.php`
   - Backend sekarang menerima `selectedCoursePayloads`.
   - Jika course dari frontend belum ada di database, backend otomatis membuat program/course tersebut lalu melakukan sync ke pivot `course_user`.

4. **Bootstrap data**
   - `backend-laravel/app/Http/Controllers/BootstrapController.php`
   - Course sekarang dikirim dengan relasi program dan diurutkan berdasarkan kode/nama.

5. **Smart Match minor fix**
   - `backend-laravel/app/Services/SmartMatchService.php`
   - Program studi juga bisa match lewat `program_name` jika `program_id` kosong.

6. **Database tambahan**
   - `studymate_dump_clean.sql` sudah ditambahi katalog akademik.
   - File terpisah `database_academic_catalog_patch.sql` juga tersedia jika database lama sudah terlanjur diimport.

## Cara pakai cepat

Jika database belum dibuat ulang:

```bash
mysql -u root -p studymate < studymate_dump_clean.sql
```

Jika database lama sudah jalan dan hanya ingin tambah katalog akademik:

```bash
mysql -u root -p studymate < database_academic_catalog_patch.sql
```

Lalu jalankan backend/frontend seperti biasa:

```bash
cd backend-laravel
composer install
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=4000
```

```bash
cd frontend
npm install
npm run dev
```

> Catatan: folder `node_modules` sengaja tidak disertakan dalam ZIP final. Install ulang dependency dengan `npm install` agar tidak terkena bug binary Vite/Rollup lintas OS.
