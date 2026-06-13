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
            $table->string('university')->nullable()->after('email');
            $table->integer('semester')->nullable()->after('program_id');
            // Ganti program_id agar bisa string (nama prodi langsung) jika bukan relasi ID
            $table->string('program_name')->nullable()->after('university');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['university', 'semester', 'program_name']);
        });
    }
};
