<?php

namespace App\Services;

use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SmartMatchService
{
    /**
     * Get matches for user (Rebuilt Version 3.0)
     */
    public function getMatchesForUser(User $user, int $limit = 10, ?string $search = null): array
    {
        try {
            // 1. Ambil ID yang harus dikecualikan (Diri sendiri + Teman)
            $friendIds = DB::table('friends')->where('user_id', $user->id)->pluck('friend_id')->all();
            $excludeIds = array_unique(array_merge([$user->id], $friendIds));

            // 2. QUERY PARTNER MATCH
            $partnerQuery = User::query()->whereNotIn('id', $excludeIds);

            if ($search) {
                $s = "%{$search}%";
                $partnerQuery->where(function ($q) use ($s) {
                    $q->where('name', 'like', $s)
                      ->orWhere('program_name', 'like', $s)
                      ->orWhere('university', 'like', $s);
                });
            }

            $partnerMatches = $partnerQuery->with(['program', 'courses'])
                ->take($limit * 2) // Ambil lebih banyak untuk difilter di memori
                ->get()
                ->map(fn (User $candidate) => $this->scorePartner($user, $candidate))
                ->sortByDesc('score')
                ->take($limit)
                ->values();

            return [
                'partnerMatches' => $partnerMatches,
                'smartMatchMeta' => [
                    'strategy' => 'Kombinasi kemiripan akademik, jadwal, dan minat.',
                    'aiMode' => 'Heuristic Matchmaking v3.0',
                    'debug' => [
                        'total_users' => User::count(),
                        'excluded_users' => count($excludeIds)
                    ]
                ],
            ];
        } catch (\Exception $e) {
            \Log::error("SmartMatch Error: " . $e->getMessage());
            return [
                'partnerMatches' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    private function scorePartner(User $user, User $candidate): array
    {
        $score = 10; // Base score
        $reasons = [];

        // Program Studi
        if (strtoupper($user->program_name ?? '') === strtoupper($candidate->program_name ?? '')) {
            $score += 25;
            $reasons[] = 'Satu program studi.';
        }

        // Universitas
        if (strtoupper($user->university ?? '') === strtoupper($candidate->university ?? '')) {
            $score += 15;
            $reasons[] = 'Satu universitas.';
        }

        // Mata Kuliah
        $sharedCourses = $candidate->courses->intersect($user->courses);
        if ($sharedCourses->count() > 0) {
            $score += min($sharedCourses->count() * 15, 40);
            $reasons[] = $sharedCourses->count() . ' mata kuliah yang sama.';
        }

        // Semester
        $gap = abs(($user->semester ?? 1) - ($candidate->semester ?? 1));
        if ($gap <= 1) {
            $score += 10;
            $reasons[] = 'Semester berdekatan.';
        }

        return [
            'user' => $candidate,
            'score' => min($score, 100),
            'confidence' => $score >= 60 ? 'Tinggi' : ($score >= 30 ? 'Sedang' : 'Cukup'),
            'reasons' => $reasons,
            'sharedCourses' => $sharedCourses->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'matchNarrative' => "Cocok karena " . (implode(', ', array_slice($reasons, 0, 2)) ?: 'profil akademik.')
        ];
    }

    private function scoreGroup(User $user, StudyGroup $group): array
    {
        $score = 15;
        $reasons = [];
        $seatsLeft = max(0, (int)$group->capacity - $group->members->count());

        if ($user->courses->contains('id', $group->course_id)) {
            $score += 40;
            $reasons[] = 'Mata kuliah sesuai.';
        }

        if ($seatsLeft > 0) {
            $score += 10;
        }

        return [
            'group' => $group,
            'score' => min($score, 100),
            'confidence' => $score >= 50 ? 'Tinggi' : 'Sedang',
            'reasons' => $reasons,
            'seatsLeft' => $seatsLeft,
            'compatibilityNarrative' => "Grup ini " . (implode(' dan ', $reasons) ?: 'menarik untuk diikuti.')
        ];
    }
}
