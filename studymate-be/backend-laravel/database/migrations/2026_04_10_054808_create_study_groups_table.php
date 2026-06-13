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
        Schema::create('study_groups', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('topic');
            $table->text('description')->nullable();
            $table->string('schedule');
            $table->string('course_id');
            $table->string('location_id');
            $table->integer('capacity');
            $table->string('owner_id');
            $table->string('status')->default('active');
            
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_groups');
    }
};
