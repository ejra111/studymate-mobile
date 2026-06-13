<?php

namespace App\Services;

use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StudyAiService
{
    public function buildStudyPlan(User $user): array
    {
        $user->loadMissing(['courses', 'studyGroups.location']);

        $courses = $user->courses->values();
        $availability = $this->normalizeAvailabilitySlots($user->availability ?? [], $courses);

        if ($courses->isEmpty()) {
            return [
                'headline' => 'Belum ada jadwal belajar yang bisa dibuat.',
                'summary' => 'Tambahkan mata kuliah aktif dulu agar sistem bisa menyusun rekomendasi jadwal belajar personal.',
                'recommendedFocusWindow' => null,
                'sessions' => [],
                'tips' => [
                    'Pilih mata kuliah aktif dari Profil Akademik.',
                    'Lengkapi minat dan semester agar prioritas belajar lebih sesuai.',
                    'Tambahkan jadwal belajar yang terhubung ke mata kuliah.',
                ],
                'source' => 'local_scheduler',
            ];
        }

        if ($availability->isEmpty()) {
            return [
                'headline' => 'Jadwal belum dibuat karena belum ada slot belajar.',
                'summary' => 'Buka Profil, pilih mata kuliah, lalu tambahkan hari, jam, dan durasi belajar untuk tiap mata kuliah.',
                'recommendedFocusWindow' => null,
                'sessions' => [],
                'tips' => [
                    'Gunakan format jadwal terstruktur: mata kuliah → hari → jam → durasi.',
                    'Tambahkan minimal satu slot untuk mata kuliah prioritas.',
                    'Gunakan slot berbeda untuk mata kuliah yang membutuhkan latihan intensif.',
                ],
                'source' => 'local_scheduler',
            ];
        }

        $courseBuckets = $courses->map(function ($course) use ($user) {
            $priority = $this->coursePriority($course->name, $user->interests ?? [], $user->bio, (int) ($user->semester ?? 1));
            return [
                'course' => $course,
                'priority' => $priority,
            ];
        })->sortByDesc('priority')->values();

        $slotsByCourse = $availability
            ->filter(fn ($slot) => !empty($slot['courseId']))
            ->groupBy('courseId');

        $genericSlots = $availability
            ->filter(fn ($slot) => empty($slot['courseId']))
            ->values();

        $sessions = [];
        foreach ($courseBuckets as $index => $bucket) {
            $course = $bucket['course'];
            $courseSlots = $slotsByCourse->get($course->id, collect());

            if ($courseSlots->isEmpty() && $genericSlots->isNotEmpty()) {
                $courseSlots = collect([$genericSlots[$index % $genericSlots->count()]]);
            }

            foreach ($courseSlots as $slot) {
                $duration = (int) ($slot['durationMinutes'] ?? ($bucket['priority'] >= 75 ? 120 : 90));
                $sessions[] = [
                    'courseId' => $course->id,
                    'courseCode' => $course->code,
                    'courseName' => $course->name,
                    'slot' => trim(($slot['day'] ?? '') . ' ' . ($slot['time'] ?? '')),
                    'day' => $slot['day'] ?? null,
                    'time' => $slot['time'] ?? null,
                    'durationMinutes' => $duration,
                    'focus' => $this->focusSuggestion($course->name),
                    'priority' => $bucket['priority'],
                    'slotSource' => !empty($slot['courseId']) ? 'course_specific' : 'generic',
                ];
            }
        }

        $sessions = collect($sessions)
            ->sortByDesc('priority')
            ->values()
            ->all();

        $focusWindow = $sessions[0]['slot'] ?? null;
        $tips = [
            'Jadwal sekarang terhubung ke mata kuliah, bukan input waktu bebas.',
            'Mulai dari mata kuliah dengan prioritas tertinggi terlebih dahulu.',
            'Pakai Smart Match untuk mencari partner yang punya mata kuliah dan slot waktu serupa.',
        ];

        if ($user->studyGroups->count() > 0) {
            $tips[] = 'Gabungkan sesi personal dengan jadwal grup agar ritme belajar lebih konsisten.';
        }

        return [
            'headline' => 'Rekomendasi jadwal belajar berbasis mata kuliah siap dipakai.',
            'summary' => 'Jadwal ini disusun dari mata kuliah aktif dan slot waktu spesifik yang kamu pilih untuk tiap mata kuliah.',
            'recommendedFocusWindow' => $focusWindow,
            'sessions' => $sessions,
            'tips' => $tips,
            'source' => 'local_scheduler',
        ];
    }

    private function normalizeAvailabilitySlots(array $availability, Collection $courses): Collection
    {
        $days = ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU'];

        return collect($availability)->map(function ($slot, $index) use ($courses, $days) {
            if (is_string($slot)) {
                $parts = preg_split('/\s+/', trim(strtoupper($slot)));
                $day = $parts[0] ?? null;
                $time = $parts[1] ?? null;

                if (!in_array($day, $days, true) || !preg_match('/^\d{2}:\d{2}$/', (string) $time)) {
                    return null;
                }

                $course = $courses->get($index % max($courses->count(), 1));
                return [
                    'courseId' => $course?->id,
                    'courseCode' => $course?->code,
                    'courseName' => $course?->name,
                    'day' => $day,
                    'time' => $time,
                    'durationMinutes' => 90,
                ];
            }

            if (!is_array($slot)) return null;

            $day = strtoupper(trim((string) ($slot['day'] ?? '')));
            $time = trim((string) ($slot['time'] ?? ''));
            $duration = (int) ($slot['durationMinutes'] ?? $slot['duration'] ?? 90);

            if (!in_array($day, $days, true) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
                return null;
            }

            $courseId = trim((string) ($slot['courseId'] ?? ''));
            $course = $courseId ? $courses->firstWhere('id', $courseId) : null;

            return [
                'courseId' => $courseId ?: null,
                'courseCode' => strtoupper(trim((string) ($slot['courseCode'] ?? $course?->code ?? ''))),
                'courseName' => trim((string) ($slot['courseName'] ?? $course?->name ?? '')),
                'day' => $day,
                'time' => $time,
                'durationMinutes' => max(30, min($duration, 240)),
            ];
        })->filter()->values();
    }

    /**
     * Summarize group discussion using Groq AI with fallback to keyword extraction
     */
    public function summarizeGroupDiscussion(StudyGroup $group, Collection $messages, bool $forceRefresh = false): array
    {
        $group->loadMissing(['course', 'members']);

        if ($messages->isEmpty()) {
            return [
                'headline' => 'Belum ada percakapan untuk diringkas.',
                'summary' => 'Mulai diskusi di chat grup terlebih dahulu.',
                'keywords' => [],
                'actionItems' => [],
                'deadlines' => [],
            ];
        }

        // Check cache (5 minutes TTL)
        $cacheKey = "group_summary_{$group->id}";
        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        // Limit to 50 most recent messages
        $recentMessages = $messages->take(50);

        // Try Groq AI first
        $apiKey = config('services.groq.api_key');
        $model = config('services.groq.model', 'llama-3.3-70b-versatile');

        if ($apiKey) {
            try {
                $result = $this->summarizeWithGroq($group, $recentMessages, $apiKey, $model);
                if ($result) {
                    Cache::put($cacheKey, $result, now()->addMinutes(5));
                    return $result;
                }
            } catch (\Exception $e) {
                Log::warning('Groq summarization failed, falling back to keyword extraction', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback to local keyword extraction
        $result = $this->summarizeLocally($group, $recentMessages);
        Cache::put($cacheKey, $result, now()->addMinutes(5));
        return $result;
    }

    /**
     * Use Groq API to generate group discussion summary
     */
    private function summarizeWithGroq(StudyGroup $group, Collection $messages, string $apiKey, string $model): ?array
    {
        $chatLog = $messages->map(function ($msg) {
            $userName = $msg->user?->name ?? 'Unknown';
            return "{$userName}: {$msg->message}";
        })->implode("\n");

        $courseName = $group->course?->name ?? 'Umum';

        $prompt = <<<PROMPT
Kamu adalah asisten AI untuk platform belajar mahasiswa bernama StudyMate.

Berikut adalah percakapan dari grup belajar "{$group->title}" (mata kuliah: {$courseName}).

PERCAKAPAN:
{$chatLog}

Buatkan ringkasan percakapan dalam Bahasa Indonesia dengan format JSON berikut:
{
  "headline": "Judul ringkasan singkat (1 kalimat)",
  "summary": "Ringkasan isi percakapan (2-3 kalimat)",
  "keywords": ["kata kunci 1", "kata kunci 2", "maksimal 6"],
  "actionItems": ["tindak lanjut 1", "tindak lanjut 2"],
  "deadlines": ["deadline/tenggat waktu yang disebutkan"]
}

PENTING: Jawab HANYA dengan JSON valid tanpa markdown formatting atau teks tambahan.
PROMPT;

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])
        ->timeout(20)
        ->post('https://api.groq.com/openai/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 600,
            'temperature' => 0.3,
        ]);

        if ($response->failed()) {
            Log::warning('Groq summary API call failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            return null;
        }

        // Clean markdown formatting if present
        $content = preg_replace('/^```json\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/i', '', $content);

        $parsed = json_decode($content, true);

        if (!$parsed || !isset($parsed['summary'])) {
            Log::warning('Groq summary JSON parse failed', ['content' => $content]);
            return null;
        }

        return [
            'headline' => $parsed['headline'] ?? 'Ringkasan AI untuk diskusi grup.',
            'summary' => $parsed['summary'],
            'keywords' => array_slice($parsed['keywords'] ?? [], 0, 6),
            'actionItems' => array_slice($parsed['actionItems'] ?? [], 0, 5),
            'deadlines' => array_slice($parsed['deadlines'] ?? [], 0, 8),
            'messageCount' => $messages->count(),
            'source' => 'groq_ai',
        ];
    }

    /**
     * Fallback: local keyword extraction based summarization
     */
    private function summarizeLocally(StudyGroup $group, Collection $messages): array
    {
        $texts = $messages->pluck('message')->filter()->values();
        $combined = strtolower($texts->implode(' '));
        $keywords = $this->extractKeywords($combined);
        $actionItems = $this->extractActionItems($texts);
        $deadlines = $this->extractDeadlines($texts);

        $summaryParts = [];
        if ($group->course?->name) {
            $summaryParts[] = 'Diskusi berfokus pada ' . strtolower($group->course->name);
        }
        if (!empty($keywords)) {
            $summaryParts[] = 'topik yang paling sering muncul adalah ' . implode(', ', array_slice($keywords, 0, 4));
        }
        if (!empty($actionItems)) {
            $summaryParts[] = 'terdapat beberapa tindak lanjut yang perlu dikerjakan';
        }
        if (!empty($deadlines)) {
            $summaryParts[] = 'serta ada penanda waktu yang perlu diperhatikan';
        }

        return [
            'headline' => 'Ringkasan AI untuk diskusi grup.',
            'summary' => ucfirst(implode(', ', $summaryParts)) . '.',
            'keywords' => $keywords,
            'actionItems' => $actionItems,
            'deadlines' => $deadlines,
            'messageCount' => $messages->count(),
            'source' => 'local_keywords',
        ];
    }

    /**
     * AI Coach - fully powered by Groq API with conversation history
     */
    public function askCoach(User $user, string $message, array $history = []): array
    {
        $user->loadMissing(['courses', 'program']);

        $apiKey = trim((string) config('services.groq.api_key'));
        $model = trim((string) config('services.groq.model', 'llama-3.3-70b-versatile')) ?: 'llama-3.3-70b-versatile';

        if (!$this->hasUsableGroqKey($apiKey)) {
            Log::info('AI Coach using local fallback because Groq API key is not configured.');
            return $this->localCoachFallback(
                $user,
                $message,
                'Mode lokal aktif karena GROQ_API_KEY belum diisi di file .env backend.'
            );
        }

        try {
            $systemPrompt = $this->buildCoachSystemPrompt($user);

            $apiMessages = [
                ['role' => 'system', 'content' => $systemPrompt],
            ];

            foreach (array_slice($history, -10) as $historyMsg) {
                $role = ($historyMsg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
                $content = trim((string) ($historyMsg['content'] ?? ''));
                if ($content !== '') {
                    $apiMessages[] = ['role' => $role, 'content' => $content];
                }
            }

            $apiMessages[] = ['role' => 'user', 'content' => $message];

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(25)
                ->retry(1, 300)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $apiMessages,
                    'max_tokens' => 800,
                    'temperature' => 0.55,
                ]);

            if ($response->failed()) {
                Log::warning('Groq API failed for coach; using local fallback.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'model' => $model,
                ]);

                return $this->localCoachFallback(
                    $user,
                    $message,
                    'Groq sedang tidak bisa dihubungi saat ini. Sistem memakai jawaban lokal sementara.'
                );
            }

            $data = $response->json();
            $aiMessage = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

            if ($aiMessage === '') {
                Log::warning('Groq returned empty coach response; using local fallback.', ['model' => $model]);
                return $this->localCoachFallback(
                    $user,
                    $message,
                    'Groq tidak mengembalikan respons. Sistem memakai jawaban lokal sementara.'
                );
            }

            return [
                'message' => $aiMessage,
                'timestamp' => now()->toIso8601String(),
                'sender' => 'AI Coach',
                'source' => 'groq_ai',
                'model' => $model,
            ];
        } catch (\Throwable $e) {
            Log::error('Groq API exception in coach; using local fallback.', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);

            return $this->localCoachFallback(
                $user,
                $message,
                'Terjadi gangguan koneksi ke Groq. Sistem memakai jawaban lokal sementara.'
            );
        }
    }

    public function aiHealth(): array
    {
        $apiKey = trim((string) config('services.groq.api_key'));
        $model = trim((string) config('services.groq.model', 'llama-3.3-70b-versatile')) ?: 'llama-3.3-70b-versatile';

        return [
            'groqConfigured' => $this->hasUsableGroqKey($apiKey),
            'model' => $model,
            'fallbackEnabled' => true,
            'message' => $this->hasUsableGroqKey($apiKey)
                ? 'Groq API key terbaca. AI Coach akan mencoba memakai Groq.'
                : 'Groq API key belum terisi. AI Coach tetap berjalan dengan mode lokal.',
        ];
    }

    private function hasUsableGroqKey(?string $apiKey): bool
    {
        $apiKey = trim((string) $apiKey);
        if ($apiKey === '') {
            return false;
        }

        $invalidValues = [
            'insert-your-groq-api-key-here',
            'isi_api_key_groq_asli',
            'your-groq-api-key',
            'null',
            'none',
        ];

        return !in_array(strtolower($apiKey), $invalidValues, true);
    }

    private function localCoachFallback(User $user, string $message, string $note): array
    {
        $user->loadMissing(['courses', 'program']);

        $question = strtolower(trim($message));
        $courses = $user->courses->take(3)->map(function ($course) {
            return trim(($course->code ? $course->code . ' - ' : '') . $course->name);
        })->values()->all();

        $courseText = !empty($courses)
            ? 'Dari profilmu, mata kuliah aktif yang bisa diprioritaskan: ' . implode(', ', $courses) . '.'
            : 'Profilmu belum punya mata kuliah aktif, jadi tambahkan dulu di Profil Akademik agar saran lebih spesifik.';

        if (str_contains($question, 'belajar') || str_contains($question, 'cara') || str_contains($question, 'ajarkan')) {
            $answer = "Bisa. Mulai dengan pola 3 langkah: (1) baca konsep inti 15 menit, (2) buat ringkasan kecil dengan bahasamu sendiri, lalu (3) kerjakan latihan atau studi kasus 30-45 menit. {$courseText}\n\nUntuk malam ini, pilih satu mata kuliah paling sulit dulu, buat target kecil, misalnya memahami 1 subbab atau menyelesaikan 3 soal. Setelah itu, pakai Smart Match untuk mencari teman dengan mata kuliah yang sama.";
        } elseif (str_contains($question, 'jadwal') || str_contains($question, 'planner') || str_contains($question, 'waktu')) {
            $answer = "Gunakan jadwal belajar pendek tapi konsisten. Rekomendasi awal: 60-90 menit per sesi, 3 kali seminggu. {$courseText}\n\nUrutan yang rapi: mata kuliah tersulit di awal minggu, mata kuliah praktik di tengah minggu, dan review/ringkasan di akhir minggu.";
        } elseif (str_contains($question, 'smart match') || str_contains($question, 'teman') || str_contains($question, 'partner')) {
            $answer = "Smart Match berguna untuk mencari teman belajar berdasarkan program studi, mata kuliah aktif, minat, dan ketersediaan waktu. {$courseText}\n\nAgar hasilnya bagus, pastikan Profil Akademik, Minat, dan Ketersediaan Waktu sudah diisi. Semakin lengkap profil, semakin kuat skor kecocokannya.";
        } else {
            $answer = "Saya bisa bantu sebagai Study Coach. {$courseText}\n\nKirim pertanyaan yang lebih spesifik, misalnya: 'buatkan jadwal belajar Basis Data', 'cara belajar Machine Learning', atau 'bagaimana mencari partner belajar yang cocok'.";
        }

        return [
            'message' => $answer,
            'timestamp' => now()->toIso8601String(),
            'sender' => 'AI Coach',
            'source' => 'local_fallback',
            'notice' => $note,
        ];
    }

    private function buildCoachSystemPrompt(User $user): string
    {
        $courseNames = $user->courses->pluck('name')->implode(', ') ?: 'belum diisi';
        $programName = $user->program?->name ?? $user->program_name ?? 'belum diisi';
        $semester = $user->semester ?? 'belum diisi';
        $interests = implode(', ', $user->interests ?? []) ?: 'belum diisi';
        $bio = $user->bio ?: 'belum diisi';

        return <<<PROMPT
Kamu adalah "AI Study Coach" di platform StudyMate, sebuah aplikasi belajar untuk mahasiswa Indonesia.

Profil mahasiswa yang sedang bertanya:
- Nama: {$user->name}
- Program Studi: {$programName}
- Semester: {$semester}
- Mata Kuliah Aktif: {$courseNames}
- Minat: {$interests}
- Bio: {$bio}

Aturan:
1. Jawab dalam Bahasa Indonesia yang ramah dan santai.
2. Berikan saran belajar yang spesifik dan actionable berdasarkan profil mahasiswa.
3. Jika ditanya tentang topik akademik (seperti programming, database, AI), berikan penjelasan yang jelas dan terstruktur.
4. Jangan terlalu panjang — maksimal 3-4 paragraf.
5. Jika relevan, sarankan fitur StudyMate seperti Smart Match atau Grup Belajar.
6. Bersikaplah supportif dan memotivasi.
PROMPT;
    }

    private function coursePriority(string $courseName, array $interests = [], ?string $bio = null, int $semester = 1): int
    {
        $priority = 55;
        $haystack = strtolower($courseName . ' ' . (string) $bio);

        foreach ($interests as $interest) {
            if ($interest && str_contains($haystack, strtolower($interest))) {
                $priority += 8;
            }
        }

        if (str_contains($haystack, 'tugas akhir') || str_contains($haystack, 'project')) {
            $priority += 10;
        }

        if ($semester >= 5) {
            $priority += 5;
        }

        return min($priority, 95);
    }

    private function focusSuggestion(string $courseName): string
    {
        $name = strtolower($courseName);

        return match (true) {
            str_contains($name, 'basis data') => 'Latihan query, ERD, dan normalisasi.',
            str_contains($name, 'web') => 'Kerjakan implementasi UI dan integrasi API.',
            str_contains($name, 'objek') => 'Ulangi konsep class, inheritance, dan practice coding.',
            str_contains($name, 'ai') || str_contains($name, 'kecerdasan') => 'Fokus pada konsep inti, studi kasus, dan evaluasi model.',
            default => 'Review materi inti, buat catatan ringkas, lalu latihan soal.',
        };
    }

    private function extractKeywords(string $text): array
    {
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? '';
        $tokens = preg_split('/\s+/', $text) ?: [];
        $stopwords = [
            'dan', 'yang', 'untuk', 'dengan', 'atau', 'saya', 'kami', 'kita', 'ini', 'itu', 'the',
            'ada', 'akan', 'jadi', 'sudah', 'belum', 'besok', 'nanti', 'grup', 'study', 'mate',
        ];

        $counts = [];
        foreach ($tokens as $token) {
            if (strlen($token) < 4 || in_array($token, $stopwords, true)) {
                continue;
            }
            $counts[$token] = ($counts[$token] ?? 0) + 1;
        }

        arsort($counts);
        return array_slice(array_keys($counts), 0, 6);
    }

    private function extractActionItems(Collection $texts): array
    {
        $patterns = '/(tolong|please|kerjakan|buat|review|cek|fix|rapikan|submit|kumpul|presentasi|update)/i';

        return $texts
            ->filter(fn ($text) => preg_match($patterns, (string) $text) === 1)
            ->map(fn ($text) => trim((string) $text))
            ->take(5)
            ->values()
            ->all();
    }

    private function extractDeadlines(Collection $texts): array
    {
        $results = [];
        $patterns = [
            '/\b(senin|selasa|rabu|kamis|jumat|sabtu|minggu|besok|lusa)\b/i',
            '/\b\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?\b/',
            '/\b\d{1,2}\.\d{2}\b/',
        ];

        foreach ($texts as $text) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, (string) $text, $matches)) {
                    foreach (($matches[0] ?? []) as $match) {
                        $results[] = $match;
                    }
                }
            }
        }

        return array_values(array_unique(array_slice($results, 0, 8)));
    }
}
