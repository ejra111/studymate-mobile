<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Program;
use App\Models\Course;
use App\Models\Location;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function summary()
    {
        return response()->json([
            'users' => User::with(['program', 'courses'])->get(),
            'programs' => Program::all(),
            'courses' => Course::with('program')->get(),
            'locations' => Location::all(),
            'activities' => Activity::latest()->limit(20)->get(),
        ]);
    }

    public function activities()
    {
        return response()->json(Activity::latest()->get());
    }

    // Programs
    public function storeProgram(Request $request)
    {
        $program = Program::create([
            'id' => (string) Str::uuid(),
            'name' => $request->name,
            'faculty' => $request->faculty,
        ]);
        return response()->json($program, 201);
    }

    public function updateProgram(Request $request, $id)
    {
        $program = Program::find($id);
        $program->update($request->only(['name', 'faculty']));
        return response()->json($program);
    }

    public function destroyProgram($id)
    {
        Program::destroy($id);
        return response()->json(null, 204);
    }

    // Courses
    public function storeCourse(Request $request)
    {
        $course = Course::create([
            'id' => (string) Str::uuid(),
            'code' => $request->code,
            'name' => $request->name,
            'program_id' => $request->programId,
        ]);
        return response()->json($course, 201);
    }

    public function updateCourse(Request $request, $id)
    {
        $course = Course::find($id);
        $course->update([
            'code' => $request->code,
            'name' => $request->name,
            'program_id' => $request->programId,
        ]);
        return response()->json($course);
    }

    public function destroyCourse($id)
    {
        Course::destroy($id);
        return response()->json(null, 204);
    }

    // Locations
    public function storeLocation(Request $request)
    {
        $location = Location::create([
            'id' => (string) Str::uuid(),
            'name' => $request->name,
            'address' => $request->address,
            'map_hint' => $request->mapHint,
        ]);
        return response()->json($location, 201);
    }

    public function updateLocation(Request $request, $id)
    {
        $location = Location::find($id);
        $location->update([
            'name' => $request->name,
            'address' => $request->address,
            'map_hint' => $request->mapHint,
        ]);
        return response()->json($location);
    }

    public function destroyLocation($id)
    {
        Location::destroy($id);
        return response()->json(null, 204);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::find($id);
        $user->update([
            'role' => $request->role,
            'name' => $request->name,
            'student_id' => $request->studentId,
            'program_id' => $request->programId,
        ]);
        return response()->json($user);
    }
}
