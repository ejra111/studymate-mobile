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
            // Make student_id required and unique
            $table->string('student_id')->unique()->change();
            
            // Add verification status
            $table->enum('verification_status', ['unverified', 'half_verified', 'fully_verified'])->default('unverified');
            
            // Add KTM fields
            $table->string('ktm_image')->nullable();
            $table->timestamp('ktm_verification_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'ktm_image', 'ktm_verification_date']);
            $table->string('student_id')->nullable()->change();
        });
    }
};
