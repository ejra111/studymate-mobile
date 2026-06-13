<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('sender_id');
            $table->string('receiver_id');
            $table->string('type'); // e.g. 'study_invite', 'group_join', etc.
            $table->text('message');
            $table->json('data')->nullable(); // extra payload
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['receiver_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_notifications');
    }
};
