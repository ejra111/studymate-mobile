<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Change ID to string to match existing UUIDs from db.json
            // Since it's a new migration on SQLite, we might have issues changing primary key.
            // Let's assume we use UUID strings for ID.
            $table->string('role')->default('student')->after('password');
            $table->string('student_id')->nullable()->after('role');
            $table->string('program_id')->nullable()->after('student_id');
            $table->text('bio')->nullable()->after('program_id');
            $table->json('interests')->nullable()->after('bio');
            $table->json('availability')->nullable()->after('interests');
            $table->string('avatar_color')->nullable()->after('availability');
            
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
            $table->dropColumn(['role', 'student_id', 'program_id', 'bio', 'interests', 'availability', 'avatar_color']);
        });
    }
};
