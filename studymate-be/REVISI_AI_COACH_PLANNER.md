# Revisi AI Coach dan Study Planner

## Ringkasan perubahan

1. **AI Coach tidak lagi mati saat `GROQ_API_KEY` kosong.**
   - Jika API key Groq belum diisi, AI Coach tetap menjawab dengan mode lokal.
   - Jika Groq error/down, backend otomatis fallback ke mode lokal.
   - Respons lokal diberi `source: local_fallback`.
   - Respons Groq asli diberi `source: groq_ai`.

2. **Endpoint status AI ditambahkan.**
   - Buka: `http://127.0.0.1:4000/api/ai/health`
   - Jika `groqConfigured: false`, berarti `.env` belum berisi API key Groq.
   - Jika `groqConfigured: true`, backend sudah membaca API key.

3. **Study Planner tidak dihapus, tetapi diganti nama agar jelas.**
   - Dari: `AI Study Planner`
   - Menjadi: `Rekomendasi Jadwal Belajar`
   - Fungsi sebenarnya: menyusun slot belajar dari mata kuliah aktif, minat, semester, dan ketersediaan waktu.
   - Ini bukan chatbot Groq. Ini scheduler lokal agar dashboard tetap berguna walaupun Groq belum aktif.

## Setup `.env` Laravel untuk Laragon tanpa password

File:
`backend-laravel/.env`

Gunakan:

```env
APP_NAME=StudyMate
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:4000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=studymate
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

GROQ_API_KEY=
GROQ_MODEL=llama-3.3-70b-versatile

FRONTEND_URL=http://localhost:5173
```

Jika ingin Groq asli aktif, isi:

```env
GROQ_API_KEY=gsk_xxxxxxxxxxxxxxxxx
```

Setelah ubah `.env`, wajib jalankan:

```bash
cd backend-laravel
php artisan config:clear
php artisan cache:clear
php artisan key:generate
php artisan serve --host=127.0.0.1 --port=4000
```

## Tes cepat

Backend:
`http://127.0.0.1:4000/api/health`

AI:
`http://127.0.0.1:4000/api/ai/health`

Frontend:
```bash
cd frontend
npm install
npm run dev
```
