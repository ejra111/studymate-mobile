<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Course;
use App\Models\GroupMessage;
use App\Models\Location;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\StudyNotification;
use App\Models\User;
use App\Services\SmartMatchService;
use App\Services\StudyAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StudyGroupController extends Controller
{
    public function __construct(
        private readonly SmartMatchService $smartMatchService,
        private readonly StudyAiService $studyAiService,
    ) {
    }

    public function index(Request $request)
    {
        $query = StudyGroup::with(['owner', 'course', 'location', 'members']);

        if ($request->filled('courseId')) {
            $query->where('course_id', $request->string('courseId'));
        }

        if ($request->filled('search')) {
            $keyword = '%' . $request->string('search') . '%';
            $query->where(function ($inner) use ($keyword) {
                $inner->where('title', 'like', $keyword)
                    ->orWhere('topic', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword);
            });
        }

        if ($request->filled('favoriteOnly') && $request->filled('userId')) {
            $query->whereHas('favoritedBy', function ($q) use ($request) {
                $q->where('users.id', $request->string('userId'));
            });
        }

        $sortBy = $request->string('sortBy', 'created_at');
        $sortOrder = $request->string('sortOrder', 'desc');
        if (in_array($sortBy, ['title', 'created_at', 'capacity', 'member_count'])) {
            if ($sortBy === 'member_count') {
                $query->withCount('members')->orderBy('members_count', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            $query->latest();
        }

        $userId = $request->string('userId', null);
        $groups = $query->get()->map(function (StudyGroup $group) use ($userId) {
            $data = $this->serializeGroup($group);
            if ($userId) {
                $data['isFavorited'] = $group->favoritedBy()->where('users.id', $userId)->exists();
            }
            return $data;
        });

        return response()->json($groups);
    }

    public function toggleFavorite(Request $request, string $id)
    {
        $group = StudyGroup::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $request->validate([
            'userId' => 'required|exists:users,id',
        ]);

        $userId = (string)$request->input('userId');
        $user = User::find($userId);

        if ($user->favoriteGroups()->where('study_groups.id', $id)->exists()) {
            $user->favoriteGroups()->detach($id);
            $isFavorited = false;
        } else {
            $user->favoriteGroups()->attach($id, ['id' => (string)Str::uuid()]);
            $isFavorited = true;
        }

        return response()->json(['isFavorited' => $isFavorited]);
    }

    public function getInviteLink(string $id)
    {
        $group = StudyGroup::with(['owner', 'course', 'location'])->find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $inviteLink = config('app.url') . '/groups/invite/' . $group->id;

        return response()->json([
            'inviteLink' => $inviteLink,
            'groupId' => $group->id,
            'groupTitle' => $group->title,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'topic' => 'required|string|max:255',
            'description' => 'nullable|string',
            'schedule' => 'required|string|max:255',
            'courseId' => 'nullable|exists:courses,id',
            'courseName' => 'nullable|string|max:255',
            'locationId' => 'nullable|exists:locations,id',
            'locationName' => 'nullable|string|max:255',
            'capacity' => 'required|integer|min:2|max:100',
            'ownerId' => 'required|exists:users,id',
        ]);

        $courseId = $this->resolveCourseId($request->input('courseId'), $request->input('courseName'));
        $locationId = $this->resolveLocationId($request->input('locationId'), $request->input('locationName'));

        if (!$courseId) {
            return response()->json(['message' => 'Mata kuliah wajib diisi.'], 422);
        }

        if (!$locationId) {
            return response()->json(['message' => 'Lokasi wajib diisi.'], 422);
        }

        $group = StudyGroup::create([
            'id' => (string) Str::uuid(),
            'title' => strtoupper(trim((string) $request->input('title'))),
            'topic' => strtoupper(trim((string) $request->input('topic'))),
            'description' => strtoupper(trim((string) $request->input('description', ''))),
            'schedule' => strtoupper(trim((string) $request->input('schedule'))),
            'course_id' => $courseId,
            'location_id' => $locationId,
            'capacity' => (int) $request->input('capacity'),
            'owner_id' => (string) $request->input('ownerId'),
            'status' => 'active',
        ]);

        $group->members()->attach($request->input('ownerId'));

        $owner = User::find($request->input('ownerId'));
        $this->logActivity($owner?->id, 'group.create', ($owner?->name ?? 'User') . ' membuat grup ' . $group->title);

        // Notify potential members (e.g., those with same courses)
        $potentialMemberIds = User::where('id', '!=', $owner->id)
            ->whereHas('courses', function ($q) use ($courseId) {
                $q->where('courses.id', $courseId);
            })
            ->pluck('id');

        foreach ($potentialMemberIds as $receiverId) {
            StudyNotification::create([
                'id' => (string) Str::uuid(),
                'sender_id' => $owner->id,
                'receiver_id' => $receiverId,
                'type' => 'group_created',
                'message' => "Grup baru '{$group->title}' dibuat untuk mata kuliah yang kamu ambil!",
                'data' => ['groupId' => $group->id]
            ]);
        }

        return response()->json($group->load(['owner', 'course', 'location', 'members']), 201);
    }

    public function update(Request $request, string $id)
    {
        $group = StudyGroup::with(['owner', 'course', 'location', 'members'])->find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $actorId = $request->input('actorId');
        if ($actorId !== $group->owner_id) {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk mengedit grup ini.'], 403);
        }

        $request->validate([
            'courseId' => 'nullable|exists:courses,id',
            'courseName' => 'nullable|string|max:255',
            'locationId' => 'nullable|exists:locations,id',
            'locationName' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:2|max:100',
        ]);

        $payload = [];
        foreach (['title', 'topic', 'description', 'schedule', 'status'] as $field) {
            if ($request->has($field)) {
                if ($field === 'status') {
                    $payload[$field] = $request->input($field);
                } else {
                    // All text fields → FULL UPPERCASE
                    $payload[$field] = strtoupper(trim((string) $request->input($field, '')));
                }
            }
        }

        if ($request->has('capacity')) {
            $payload['capacity'] = (int) $request->input('capacity');
        }

        if ($request->has('courseId') || $request->has('courseName')) {
            $resolved = $this->resolveCourseId($request->input('courseId'), $request->input('courseName'));
            if ($resolved) {
                $payload['course_id'] = $resolved;
            }
        }

        if ($request->has('locationId') || $request->has('locationName')) {
            $resolved = $this->resolveLocationId($request->input('locationId'), $request->input('locationName'));
            if ($resolved) {
                $payload['location_id'] = $resolved;
            }
        }

        $group->update($payload);

        $actorId = $request->input('actorId', $group->owner_id);
        $actorName = $request->input('actorName', 'User');
        $this->logActivity($actorId, 'group.update', $actorName . ' memperbarui grup ' . $group->title);

        return response()->json($group->fresh()->load(['owner', 'course', 'location', 'members']));
    }

    public function destroy(Request $request, string $id)
    {
        $group = StudyGroup::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $actorId = $request->query('actorId');
        if ($actorId !== $group->owner_id) {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk menghapus grup ini.'], 403);
        }

        $title = $group->title;
        $group->delete();

        $this->logActivity($actorId, 'group.delete', 'Grup ' . $title . ' dihapus');

        return response()->json(null, 204);
    }

    public function join(Request $request, string $id)
    {
        $group = StudyGroup::with('members')->find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $request->validate([
            'userId' => 'required|exists:users,id',
        ]);

        $userId = (string) $request->input('userId');

        if ($group->members()->where('users.id', $userId)->exists()) {
            return response()->json(['message' => 'Kamu sudah menjadi anggota grup ini.'], 409);
        }

        if ($group->members()->count() >= (int) $group->capacity) {
            return response()->json(['message' => 'Grup sudah penuh.'], 400);
        }

        $group->members()->attach($userId);
        $user = User::find($userId);
        $this->logActivity($userId, 'group.join', ($user?->name ?? 'User') . ' bergabung ke grup ' . $group->title);

        // Notify all existing members
        $memberIds = $group->members()->where('users.id', '!=', $userId)->pluck('users.id');

        foreach ($memberIds as $receiverId) {
            StudyNotification::create([
                'id' => (string) Str::uuid(),
                'sender_id' => $userId,
                'receiver_id' => $receiverId,
                'type' => 'group_join',
                'message' => $receiverId === $group->owner_id 
                    ? "{$user->name} bergabung ke grup '{$group->title}' Anda."
                    : "{$user->name} bergabung ke grup '{$group->title}' yang kamu ikuti.",
                'data' => ['groupId' => $group->id]
            ]);
        }

        return response()->json($group->fresh()->load(['owner', 'course', 'location', 'members']));
    }

    public function leave(Request $request, string $id)
    {
        $group = StudyGroup::with('members')->find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $request->validate([
            'userId' => 'required|exists:users,id',
        ]);

        $userId = (string) $request->input('userId');

        if ($group->owner_id === $userId) {
            return response()->json(['message' => 'Pemilik grup tidak bisa keluar dari grup. Gunakan hapus grup jika ingin membubarkan grup.'], 400);
        }

        if (!$group->members()->where('users.id', $userId)->exists()) {
            return response()->json(['message' => 'Kamu bukan anggota grup ini.'], 404);
        }

        $group->members()->detach($userId);
        
        $user = User::find($userId);
        $this->logActivity($userId, 'group.leave', ($user?->name ?? 'User') . ' keluar dari grup ' . $group->title);

        return response()->json(['message' => 'Berhasil keluar dari grup.']);
    }

    public function messages(Request $request, string $id)
    {
        if (!$this->ensureGroupMessagesTable()) {
            return response()->json([], 200);
        }

        $group = StudyGroup::with('members')->find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $userId = (string) $request->query('userId', '');
        if ($userId === '') {
            return response()->json(['message' => 'userId wajib diisi.'], 400);
        }

        if (!$group->members()->where('users.id', $userId)->exists()) {
            return response()->json(['message' => 'Kamu bukan anggota grup ini.'], 403);
        }

        $messages = GroupMessage::query()
            ->where('study_group_id', $group->id)
            ->with('user')
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->map(function (GroupMessage $message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'createdAt' => optional($message->created_at)->toISOString(),
                    'user' => $message->user ? [
                        'id' => $message->user->id,
                        'name' => $message->user->name,
                        'avatarColor' => $message->user->avatarColor,
                        'avatarUrl' => $message->user->avatarUrl,
                    ] : null,
                ];
            });

        return response()->json($messages);
    }

    public function postMessage(Request $request, string $id)
    {
        if (!$this->ensureGroupMessagesTable()) {
            return response()->json(['message' => 'Fitur chat belum siap. Buat tabel group_messages terlebih dahulu.'], 500);
        }

        $group = StudyGroup::with('members')->find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $request->validate([
            'userId' => 'required|exists:users,id',
            'message' => 'required|string|max:1000',
        ]);

        $userId = (string) $request->input('userId');
        if (!$group->members()->where('users.id', $userId)->exists()) {
            return response()->json(['message' => 'Kamu bukan anggota grup ini.'], 403);
        }

        $sender = User::find($userId);
        
        $message = GroupMessage::create([
            'id' => (string) Str::uuid(),
            'study_group_id' => $group->id,
            'user_id' => $userId,
            'message' => trim((string) $request->input('message')),
        ]);

        // Notify other members
        try {
            $otherMemberIds = $group->members->pluck('id')->reject(fn ($id) => $id === $userId);
            $notifications = [];
            $now = now();
            
            foreach ($otherMemberIds as $mId) {
                $notifications[] = [
                    'id' => (string) Str::uuid(),
                    'sender_id' => $userId,
                    'receiver_id' => $mId,
                    'type' => 'group_activity',
                    'message' => "Pesan baru di grup '{$group->title}' dari " . ($sender?->name ?? 'Seseorang'),
                    'data' => json_encode(['groupId' => $group->id]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($notifications)) {
                StudyNotification::insert($notifications);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the message sending
            \Illuminate\Support\Facades\Log::error('Failed to send group notifications: ' . $e->getMessage());
        }

        // Invalidate summary cache
        Cache::forget("group_summary_{$group->id}"); 

        return response()->json([
            'id' => $message->id,
            'message' => $message->message,
            'createdAt' => optional($message->created_at)->toISOString(),
            'user' => [
                'id' => $userId,
                'name' => $sender?->name,
                'avatarColor' => $sender?->avatarColor,
                'avatarUrl' => $sender?->avatarUrl,
            ],
        ], 201);
    }

    public function getGoldenHour(string $id)
    {
        $group = StudyGroup::with('members')->find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $slotCounter = [];
        foreach ($group->members as $member) {
            foreach (($member->availability ?? []) as $slot) {
                $slotCounter[$slot] = ($slotCounter[$slot] ?? 0) + 1;
            }
        }

        if (empty($slotCounter)) {
            return response()->json([
                'headline' => 'Belum cukup data availability.',
                'bestSlot' => null,
                'coverage' => 0,
                'alternatives' => [],
            ]);
        }

        arsort($slotCounter);
        $bestSlot = array_key_first($slotCounter);
        $bestCount = (int) ($slotCounter[$bestSlot] ?? 0);
        $memberCount = max($group->members->count(), 1);

        return response()->json([
            'headline' => 'Golden Hour berhasil dihitung.',
            'bestSlot' => $bestSlot,
            'coverage' => (int) round(($bestCount / $memberCount) * 100),
            'alternatives' => collect($slotCounter)
                ->take(4)
                ->map(fn ($count, $slot) => [
                    'slot' => $slot,
                    'count' => $count,
                    'coverage' => (int) round(($count / $memberCount) * 100),
                ])
                ->values(),
        ]);
    }

    public function compatibility(Request $request, string $id)
    {
        $group = StudyGroup::with(['owner', 'course', 'location', 'members'])->find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $userId = (string) $request->query('userId', '');
        if ($userId === '') {
            return response()->json(['message' => 'userId wajib diisi.'], 400);
        }

        $user = User::with(['courses', 'studyGroups.location', 'studyGroups.members'])->find($userId);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        return response()->json($this->smartMatchService->buildGroupCompatibility($user, $group));
    }

    public function summary(Request $request, string $id)
    {
        if (!$this->ensureGroupMessagesTable()) {
            return response()->json([
                'headline' => 'Ringkasan AI belum bisa dibuat.',
                'summary' => 'Tabel group_messages belum tersedia.',
                'keywords' => [],
                'actionItems' => [],
                'deadlines' => [],
            ]);
        }

        $group = StudyGroup::with(['members', 'course'])->find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan.'], 404);
        }

        $userId = (string) $request->query('userId', '');
        if ($userId === '' || !$group->members()->where('users.id', $userId)->exists()) {
            return response()->json(['message' => 'Hanya anggota grup yang dapat melihat ringkasan AI.'], 403);
        }

        $forceRefresh = $request->boolean('force', false);

        $messages = GroupMessage::query()
            ->where('study_group_id', $group->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($this->studyAiService->summarizeGroupDiscussion($group, $messages, $forceRefresh));
    }

    private function resolveCourseId(?string $courseId, ?string $courseName): ?string
    {
        if ($courseId) {
            return $courseId;
        }

        $name = strtoupper(trim((string) $courseName));
        $name = preg_replace('/\s+/', ' ', $name) ?? '';
        if ($name === '') {
            return null;
        }

        $existing = Course::query()->whereRaw('UPPER(name) = ?', [$name])->first();
        if ($existing) {
            return $existing->id;
        }

        $program = Program::find('prog-general');
        if (!$program) {
            $program = Program::create([
                'id' => 'prog-general',
                'name' => 'UMUM',
                'faculty' => 'UMUM',
            ]);
        }

        $base = preg_replace('/[^A-Z0-9]/', '', $name) ?: 'COURSE';
        $code = substr($base, 0, 8) . strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 4));

        $course = Course::create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => $name,
            'program_id' => $program->id,
        ]);

        return $course->id;
    }

    private function resolveLocationId(?string $locationId, ?string $locationName): ?string
    {
        if ($locationId) {
            return $locationId;
        }

        $name = strtoupper(trim((string) $locationName));
        $name = preg_replace('/\s+/', ' ', $name) ?? '';
        if ($name === '') {
            return null;
        }

        $existing = Location::query()->whereRaw('UPPER(name) = ?', [$name])->first();
        if ($existing) {
            return $existing->id;
        }

        $location = Location::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'address' => '-',
            'map_hint' => null,
        ]);

        return $location->id;
    }

    private function ensureGroupMessagesTable(): bool
    {
        return Schema::hasTable('group_messages');
    }

    private function getGroupMessageCount(string $groupId): int
    {
        try {
            return GroupMessage::where('study_group_id', $groupId)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function serializeGroup(StudyGroup $group): array
    {
        return array_merge($group->toArray(), [
            'seatsLeft' => max(((int) $group->capacity) - $group->members->count(), 0),
        ]);
    }

    private function logActivity(?string $actorId, string $type, string $message): void
    {
        Activity::create([
            'id' => (string) Str::uuid(),
            'actor_id' => $actorId,
            'type' => $type,
            'message' => $message,
        ]);
    }
}
