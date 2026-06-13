<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $json = file_get_contents(base_path('../backend/data/db.json'));
        $data = json_decode($json, true);

        foreach ($data['programs'] as $program) {
            \App\Models\Program::create($program);
        }

        foreach ($data['courses'] as $course) {
            \App\Models\Course::create([
                'id' => $course['id'],
                'code' => $course['code'],
                'name' => $course['name'],
                'program_id' => $course['programId'],
            ]);
        }

        foreach ($data['locations'] as $location) {
            \App\Models\Location::create([
                'id' => $location['id'],
                'name' => $location['name'],
                'address' => $location['address'],
                'map_hint' => $location['mapHint'],
            ]);
        }

        foreach ($data['users'] as $user) {
            $courseIds = $user['courseIds'] ?? [];
            
            $dbUser = \App\Models\User::create([
                'id' => $user['id'],
                'role' => $user['role'],
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => \Illuminate\Support\Facades\Hash::make($user['password']),
                'student_id' => $user['studentId'],
                'program_id' => $user['programId'],
                'bio' => $user['bio'],
                'interests' => $user['interests'],
                'availability' => $user['availability'],
                'avatar_color' => $user['avatarColor'],
            ]);
            
            if (!empty($courseIds)) {
                $dbUser->courses()->attach($courseIds);
            }
        }

        foreach ($data['groups'] as $group) {
            $memberIds = $group['memberIds'] ?? [];
            
            $dbGroup = \App\Models\StudyGroup::create([
                'id' => $group['id'],
                'title' => $group['title'],
                'topic' => $group['topic'],
                'description' => $group['description'],
                'schedule' => $group['schedule'],
                'course_id' => $group['courseId'],
                'location_id' => $group['locationId'],
                'capacity' => $group['capacity'],
                'owner_id' => $group['ownerId'],
                'status' => $group['status'],
            ]);
            
            if (!empty($memberIds)) {
                $dbGroup->members()->attach($memberIds);
            }
        }

        foreach ($data['activities'] as $activity) {
            \App\Models\Activity::create([
                'id' => $activity['id'],
                'actor_id' => $activity['actorId'] ?? null,
                'type' => $activity['type'],
                'message' => $activity['message'],
            ]);
        }
    }
}
