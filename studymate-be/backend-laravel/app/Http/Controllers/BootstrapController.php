<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Course;
use App\Models\Location;

class BootstrapController extends Controller
{
    public function index()
    {
        return response()->json([
            'programs' => Program::orderBy('name')->get(),
            'courses' => Course::with('program')
                ->orderBy('code')
                ->orderBy('name')
                ->get(),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }
}
