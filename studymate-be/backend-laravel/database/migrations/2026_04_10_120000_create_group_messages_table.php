<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_messages')) {
            return;
        }

        Schema::create('group_messages', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('study_group_id');
            $table->string('user_id');
            $table->text('message');
            $table->timestamps();

            $table->foreign('study_group_id')->references('id')->on('study_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['study_group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_messages');
    }
};
