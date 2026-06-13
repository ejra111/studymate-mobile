# Revisi Jadwal Belajar per Mata Kuliah

Revisi ini mengganti input bebas `Ketersediaan Waktu` menjadi input terstruktur:

1. User memilih mata kuliah aktif.
2. User memilih hari.
3. User memilih jam mulai.
4. User memilih durasi.
5. Sistem menyimpan slot sebagai objek yang jelas terhubung ke mata kuliah.

Contoh data yang disimpan:

```json
{
  "courseId": "course-if3302",
  "courseCode": "IF3302",
  "courseName": "Kecerdasan Buatan",
  "day": "SENIN",
  "time": "19:00",
  "durationMinutes": 90
}
```

Backend `StudyAiService` juga sudah disesuaikan agar rekomendasi jadwal belajar tidak lagi asal memasangkan slot waktu, tetapi membaca slot yang sudah ditautkan ke mata kuliah.

Tidak perlu migrasi atau import database ulang. Field `availability` lama tetap dipakai karena sudah bertipe JSON/array.
