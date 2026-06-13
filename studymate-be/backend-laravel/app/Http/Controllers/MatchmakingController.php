<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SmartMatchService;
use Illuminate\Http\Request;

class MatchmakingController extends Controller
{
    public function __construct(private readonly SmartMatchService $smartMatchService)
    {
    }

    public function index(Request $request, string $userId)
    {
        $user = User::with(['program', 'courses', 'studyGroups.location', 'studyGroups.members'])->find($userId);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        $search = $request->query('search');
        $limit = $request->query('limit', 20);
        
        return response()->json($this->smartMatchService->getMatchesForUser($user, (int)$limit, $search));
    }
}
