<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudyAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudyAiController extends Controller
{
    public function __construct(private readonly StudyAiService $studyAiService)
    {
    }

    public function studyPlan(string $id)
    {
        $user = User::with(['courses', 'studyGroups.location'])->find($id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        return response()->json($this->studyAiService->buildStudyPlan($user));
    }

    public function askCoach(Request $request, string $id)
    {
        Log::info("AI Coach requested for user: " . $id);
        $user = User::with(['courses', 'program'])->find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $message = $request->input('message');
        if (!$message) return response()->json(['message' => 'Message is required'], 400);

        // Accept conversation history from frontend for multi-turn context
        $history = $request->input('history', []);
        if (!is_array($history)) {
            $history = [];
        }

        return response()->json($this->studyAiService->askCoach($user, $message, $history));
    }

    public function health()
    {
        return response()->json($this->studyAiService->aiHealth());
    }
}
