<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Program;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', strtolower(trim($request->email)))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        Activity::create([
            'id' => (string) Str::uuid(),
            'actor_id' => $user->id,
            'type' => 'auth.login',
            'message' => "{$user->name} login ke platform",
        ]);

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => "studymate-token-{$user->id}",
            'user' => $user->load(['program', 'courses']),
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'university' => 'required|string',
            'programName' => 'required|string',
            'semester' => 'required|integer',
            'password' => 'required|min:6|confirmed',
            'studentId' => 'required|string|unique:users,student_id',
        ]);

        $programName = strtoupper(trim((string) $request->programName));
        $matchedProgram = Program::whereRaw('UPPER(name) = ?', [$programName])->first();

        $palette = ['#7c3aed', '#2563eb', '#db2777', '#0f766e', '#ea580c', '#0891b2'];
        
        $user = User::create([
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'name' => $request->name,
            'email' => strtolower(trim($request->email)),
            'university' => strtoupper($request->university),
            'program_name' => $programName,
            'program_id' => $matchedProgram?->id,
            'semester' => $request->semester,
            'password' => Hash::make($request->password),
            'student_id' => $request->studentId,
            'bio' => 'Mahasiswa aktif yang siap belajar lebih terarah bersama partner yang tepat.',
            'interests' => [],
            'availability' => [],
            'avatar_color' => $palette[array_rand($palette)],
        ]);

        Activity::create([
            'id' => (string) Str::uuid(),
            'actor_id' => $user->id,
            'type' => 'auth.register',
            'message' => "{$user->name} membuat akun baru",
        ]);

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan login dengan akun yang baru dibuat.',
            'user' => $user->load(['program', 'courses']),
        ], 201);
    }
}
