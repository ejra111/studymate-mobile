# UI Final Patch

Perubahan:
- Bagian Jadwal Belajar di halaman Profil dihapus total.
- Profil hanya berisi Data Utama serta Akademik & Minat.
- Tampilan mata kuliah di Profil tidak lagi menampilkan kode mata kuliah; kode tetap disimpan secara internal untuk backend dan Smart Match.
- Tampilan mata kuliah pada Rekomendasi Jadwal Belajar di Dashboard juga tidak lagi menampilkan kode.
- Tampilan shared course di Smart Match tidak lagi menampilkan kode mata kuliah.
- Backend, database, dan konfigurasi Groq tidak diubah.

Catatan:
- File .env tidak disertakan dalam ZIP. Gunakan .env lokal yang sudah berjalan.
- Jika APP_KEY kosong, jalankan `php artisan key:generate` di folder backend-laravel.
