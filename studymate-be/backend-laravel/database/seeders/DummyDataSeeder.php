<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Location;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Programs
        $inf = Program::create(['id' => 'prog-inf', 'name' => 'Informatika', 'faculty' => 'Fakultas Teknik']);
        $si = Program::create(['id' => 'prog-si', 'name' => 'Sistem Informasi', 'faculty' => 'Fakultas Ilmu Komputer']);
        $dkv = Program::create(['id' => 'prog-dkv', 'name' => 'Desain Komunikasi Visual', 'faculty' => 'Fakultas Desain']);

        // 2. Courses
        $c1 = Course::create(['id' => 'c-web', 'code' => 'IF101', 'name' => 'Pemrograman Web', 'program_id' => $inf->id]);
        $c2 = Course::create(['id' => 'c-ai', 'code' => 'IF102', 'name' => 'Kecerdasan Buatan', 'program_id' => $inf->id]);
        $c3 = Course::create(['id' => 'c-db', 'code' => 'SI101', 'name' => 'Basis Data', 'program_id' => $si->id]);
        $c4 = Course::create(['id' => 'c-uiux', 'code' => 'DKV101', 'name' => 'UI/UX Design', 'program_id' => $dkv->id]);

        // 3. Locations
        $l1 = Location::create(['id' => 'l-lib', 'name' => 'Perpustakaan Pusat', 'address' => 'Gedung A Lantai 2', 'map_hint' => 'Dekat pintu masuk']);
        $l2 = Location::create(['id' => 'l-cafe', 'name' => 'Kantin Teknik', 'address' => 'Area Belakang Kampus', 'map_hint' => 'Meja pojok kiri']);

        // 4. Users
        $u1 = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Budi Santoso',
            'email' => 'budi@student.ac.id',
            'password' => Hash::make('password'),
            'role' => 'student',
            'university' => 'Universitas Indonesia',
            'program_id' => $inf->id,
            'semester' => 4,
            'bio' => 'Suka belajar web development dan AI.',
            'interests' => ['Web Dev', 'Machine Learning', 'Python'],
            'availability' => ['Senin Pagi', 'Rabu Sore', 'Jumat Malam'],
            'avatar_color' => '#3b82f6'
        ]);
        $u1->courses()->attach([$c1->id, $c2->id]);

        $u2 = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Siti Aminah',
            'email' => 'siti@student.ac.id',
            'password' => Hash::make('password'),
            'role' => 'student',
            'university' => 'Universitas Indonesia',
            'program_id' => $inf->id,
            'semester' => 4,
            'bio' => 'Fokus ke frontend dan desain.',
            'interests' => ['Web Dev', 'UI/UX', 'React'],
            'availability' => ['Senin Pagi', 'Kamis Siang'],
            'avatar_color' => '#ec4899'
        ]);
        $u2->courses()->attach([$c1->id, $c4->id]);

        $u3 = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Andi Wijaya',
            'email' => 'andi@student.ac.id',
            'password' => Hash::make('password'),
            'role' => 'student',
            'university' => 'Universitas Indonesia',
            'program_id' => $si->id,
            'semester' => 6,
            'bio' => 'Ahli database dan sistem informasi.',
            'interests' => ['SQL', 'Data Science', 'Python'],
            'availability' => ['Rabu Sore', 'Sabtu Pagi'],
            'avatar_color' => '#10b981'
        ]);
        $u3->courses()->attach([$c3->id, $c2->id]);

        // 5. Groups
        $g1 = StudyGroup::create([
            'id' => (string) Str::uuid(),
            'title' => 'BELAJAR LARAVEL BERSAMA',
            'topic' => 'BACKEND DEVELOPMENT',
            'description' => 'Mempelajari Laravel dari nol sampai mahir.',
            'schedule' => 'RABU SORE',
            'course_id' => $c1->id,
            'location_id' => $l1->id,
            'capacity' => 5,
            'owner_id' => $u1->id,
            'status' => 'active'
        ]);
        $g1->members()->attach([$u1->id, $u2->id]);

        $g2 = StudyGroup::create([
            'id' => (string) Str::uuid(),
            'title' => 'AI & MACHINE LEARNING',
            'topic' => 'KECERDASAN BUATAN',
            'description' => 'Diskusi tentang neural networks.',
            'schedule' => 'JUMAT MALAM',
            'course_id' => $c2->id,
            'location_id' => $l2->id,
            'capacity' => 10,
            'owner_id' => $u1->id,
            'status' => 'active'
        ]);
        $g2->members()->attach([$u1->id, $u3->id]);
    }
}
