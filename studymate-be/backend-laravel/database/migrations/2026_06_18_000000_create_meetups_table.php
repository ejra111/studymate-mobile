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
        Schema::create('meetups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('creator_id');
            $table->uuid('study_group_id')->nullable();
            
            $table->string('title');
            $table->text('description')->nullable();
            
            $table->dateTime('meeting_date');
            $table->string('meeting_time');
            $table->integer('estimated_duration'); // in minutes
            
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('location_name');
            
            $table->string('status')->default('PENDING'); // PENDING, ACCEPTED, ACTIVE, STARTED, FINISHED
            
            $table->timestamps();
            
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('study_group_id')->references('id')->on('study_groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetups');
    }
};
