<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Program;
use App\Models\Activity;
use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Services\SmartMatchService;
use App\Services\OcrService;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function show($id)
    {
        $user = User::with(['program', 'courses'])->find($id);
        if (!$user) return response()->json(['message' => 'User tidak ditemukan.'], 404);
        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) return response()->json(['message' => 'User tidak ditemukan.'], 404);

        // Validate request
        $request->validate([
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'studentId' => 'sometimes|string|unique:users,student_id,' . $user->id,
        ]);

        $data = $request->only([
            'name',
            'email',
            'university',
            'programName',
            'programPayload',
            'semester',
            'studentId',
            'programId',
            'bio',
            'interests',
            'courseIds',
            'courseCodes',
            'selectedCoursePayloads',
            'availability',
            'avatarColor',
            'avatarUrl'
        ]);

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['email'])) $updateData['email'] = strtolower(trim($data['email']));
        if (isset($data['university'])) $updateData['university'] = strtoupper($data['university']);
        if (isset($data['semester'])) $updateData['semester'] = (int) $data['semester'];
        if (isset($data['studentId'])) $updateData['student_id'] = $data['studentId'];
        if (isset($data['bio'])) $updateData['bio'] = $data['bio'];
        if (isset($data['interests'])) {
            $updateData['interests'] = collect($data['interests'])
                ->map(fn ($item) => strtoupper(trim((string) $item)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }
        if (isset($data['availability'])) $updateData['availability'] = $data['availability'];
        if (isset($data['avatarColor'])) $updateData['avatar_color'] = $data['avatarColor'];
        if (isset($data['avatarUrl'])) $updateData['avatar_url'] = $data['avatarUrl'];

        if (!empty($data['programPayload']['id'])) {
            $programPayload = $data['programPayload'];
            Program::updateOrCreate(
                ['id' => $programPayload['id']],
                [
                    'name' => $programPayload['name'] ?? ($data['programName'] ?? 'Program Umum'),
                    'faculty' => $programPayload['faculty'] ?? 'Fakultas Umum',
                ]
            );
        }

        if (!empty($data['programId'])) {
            $program = Program::find($data['programId']);
            $updateData['program_id'] = $data['programId'];
            $updateData['program_name'] = strtoupper($program?->name ?? ($data['programName'] ?? ''));
        } elseif (isset($data['programName'])) {
            $normalizedProgramName = strtoupper(trim((string) $data['programName']));
            $matchedProgram = Program::whereRaw('UPPER(name) = ?', [$normalizedProgramName])->first();
            $updateData['program_name'] = $normalizedProgramName;
            if ($matchedProgram) {
                $updateData['program_id'] = $matchedProgram->id;
            }
        }

        $user->update($updateData);

        $shouldSyncCourses = array_key_exists('courseIds', $data)
            || array_key_exists('courseCodes', $data)
            || array_key_exists('selectedCoursePayloads', $data);

        if ($shouldSyncCourses) {
            $courseIds = collect($data['courseIds'] ?? [])
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->values();

            foreach (($data['selectedCoursePayloads'] ?? []) as $coursePayload) {
                if (empty($coursePayload['id']) || empty($coursePayload['code']) || empty($coursePayload['name'])) {
                    continue;
                }

                $programId = $coursePayload['programId'] ?? $user->program_id ?? 'prog-general';
                Program::firstOrCreate(
                    ['id' => $programId],
                    [
                        'name' => $coursePayload['programName'] ?? 'Program Umum',
                        'faculty' => $coursePayload['faculty'] ?? 'Fakultas Umum',
                    ]
                );

                $course = Course::updateOrCreate(
                    ['id' => $coursePayload['id']],
                    [
                        'code' => strtoupper(trim((string) $coursePayload['code'])),
                        'name' => trim((string) $coursePayload['name']),
                        'program_id' => $programId,
                    ]
                );

                $courseIds->push($course->id);
            }

            foreach (($data['courseCodes'] ?? []) as $code) {
                $course = Course::whereRaw('UPPER(code) = ?', [strtoupper(trim((string) $code))])->first();
                if ($course) $courseIds->push($course->id);
            }

            $validCourseIds = Course::whereIn('id', $courseIds->unique()->values()->all())
                ->pluck('id')
                ->all();

            $user->courses()->sync($validCourseIds);
        }

        $user = $user->fresh(['program', 'courses']);

        Activity::create([
            'id' => (string) Str::uuid(),
            'actor_id' => $user->id,
            'type' => 'profile.update',
            'message' => "{$user->name} memperbarui profil akademik",
        ]);

        \App\Events\UserProfileUpdated::dispatch($user);

        return response()->json($user);
    }

    public function uploadAvatar(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) return response()->json(['message' => 'User tidak ditemukan.'], 404);

        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $file = $request->file('avatar');
        $dir = public_path('avatars');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $name);
        $url = rtrim($request->getSchemeAndHttpHost(), '/') . "/avatars/{$name}";

        $old = $user->avatar_url;
        if ($old) {
            $basename = basename(parse_url($old, PHP_URL_PATH) ?? '');
            if ($basename) {
                $oldPath = public_path('avatars/' . $basename);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }

        $user->avatar_url = $url;
        // Only update to half_verified if not already fully verified
        if ($user->verification_status !== 'fully_verified') {
            $user->verification_status = 'half_verified';
        }
        $user->save();

        Activity::create([
            'id' => (string) Str::uuid(),
            'actor_id' => $user->id,
            'type' => 'profile.avatar.update',
            'message' => "{$user->name} memperbarui foto profil",
        ]);

        \App\Events\UserProfileUpdated::dispatch($user);

        return response()->json($user->fresh()->load(['program', 'courses']));
    }

    public function uploadKtm(Request $request, $id, OcrService $ocrService)
    {
        $user = User::find($id);
        if (!$user) return response()->json(['message' => 'User tidak ditemukan.'], 404);

        Log::info('Starting KTM upload', ['user_id' => $user->id, 'user_name' => $user->name]);

        $request->validate([
            'ktm' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $file = $request->file('ktm');
        $name = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        // Store in private storage
        $path = $file->storeAs('ktm', $name, 'local');
        $fullPath = \Storage::disk('local')->path($path);
        Log::info('KTM file stored', ['user_id' => $user->id, 'path' => $path, 'full_path' => $fullPath]);

        // Verify NIM using OCR
        $isVerified = $ocrService->verifyNim($fullPath, $user->student_id);
        Log::info('KTM OCR verification result', ['user_id' => $user->id, 'is_verified' => $isVerified]);

        // Delete old KTM if exists - try multiple ways
        if ($user->ktm_image) {
            $oldPathToDelete = $user->ktm_image;
            // If old path is a full public path (from before migration), convert to relative
            if (str_starts_with($oldPathToDelete, public_path())) {
                $oldPathToDelete = 'ktm/' . basename($oldPathToDelete);
            } elseif (str_starts_with($oldPathToDelete, storage_path('app/private/'))) {
                $oldPathToDelete = substr($oldPathToDelete, strlen(storage_path('app/private/')));
            } elseif (str_starts_with($oldPathToDelete, storage_path('app/'))) {
                $oldPathToDelete = substr($oldPathToDelete, strlen(storage_path('app/')));
            }
            
            if (\Storage::disk('local')->exists($oldPathToDelete)) {
                \Storage::disk('local')->delete($oldPathToDelete);
                Log::info('Old KTM file deleted', ['user_id' => $user->id, 'old_path' => $oldPathToDelete]);
            }

            // Also try deleting from public folder just in case
            $publicOldPath = public_path('ktm/' . basename($oldPathToDelete));
            if (file_exists($publicOldPath)) {
                @unlink($publicOldPath);
                Log::info('Old KTM file deleted from public folder', ['user_id' => $user->id, 'path' => $publicOldPath]);
            }
        }

        $user->ktm_image = $path;

        if ($isVerified) {
            $user->verification_status = 'fully_verified';
            $user->ktm_verification_date = now();
            
            Activity::create([
                'id' => (string) Str::uuid(),
                'actor_id' => $user->id,
                'type' => 'profile.ktm.verified',
                'message' => "{$user->name} berhasil memverifikasi KTM",
            ]);
        } else {
            Activity::create([
                'id' => (string) Str::uuid(),
                'actor_id' => $user->id,
                'type' => 'profile.ktm.uploaded',
                'message' => "{$user->name} mengunggah KTM",
            ]);
        }

        $user->save();
        Log::info('KTM upload complete', ['user_id' => $user->id, 'verification_status' => $user->verification_status]);

        return response()->json([
            'user' => $user->fresh()->load(['program', 'courses']),
            'verified' => $isVerified,
            'message' => $isVerified 
                ? 'KTM berhasil diverifikasi! Akun Anda sekarang Fully Verified.' 
                : 'KTM diunggah, namun NIM tidak dapat diverifikasi. Silakan coba foto yang lebih jelas.'
        ]);
    }

    public function dashboard($userId)
    {
        $user = User::with(['program', 'courses', 'studyGroups', 'friends'])->find($userId);
        if (!$user) {
            \Log::error("Dashboard Error: User {$userId} not found");
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        // 1. Groups Joined (count members in pivot table for this user)
        $joinedCount = (int) \DB::table('group_user')->where('user_id', $userId)->count();
        
        // 2. Groups Created
        $createdCount = (int) \DB::table('study_groups')->where('owner_id', $userId)->count();

        // 3. Courses Selected
        $courseCount = (int) \DB::table('course_user')->where('user_id', $userId)->count();

        // 4. Smart Match Recommendations (Using Service V3.0)
        $smartMatchService = app(SmartMatchService::class);
        $matchResult = $smartMatchService->getMatchesForUser($user, 6);
        $candidates = $matchResult['partnerMatches'] ?? [];

        // 5. Compatibility Signal (Highest match score only)
        $compatibilitySignal = 0;
        if (!empty($candidates)) {
            $compatibilitySignal = (int) ($candidates[0]['score'] ?? 0);
        }

        return response()->json([
            'user' => $user,
            'stats' => [
                'joinedGroups' => $joinedCount,
                'createdGroups' => $createdCount,
                'selectedCourses' => $courseCount,
                'compatibilitySignal' => $compatibilitySignal,
            ],
            'recommendations' => $candidates,
            'upcomingGroups' => $user->studyGroups()
                ->with(['owner', 'course', 'location', 'members'])
                ->latest()
                ->take(6)
                ->get(),
            'friends' => $user->friends->map(function ($f) {
                return [
                    'id' => $f->id,
                    'name' => $f->name,
                    'avatarColor' => $f->avatarColor,
                    'avatarUrl' => $f->avatarUrl,
                    'program' => $f->program_name,
                ];
            }),
            'recentActivities' => Activity::where('actor_id', $userId)->latest()->limit(8)->get(),
        ]);
    }

    public function friends($userId)
    {
        $user = User::with('friends')->find($userId);
        if (!$user) return response()->json(['message' => 'User tidak ditemukan.'], 404);
        return response()->json($user->friends);
    }
}
