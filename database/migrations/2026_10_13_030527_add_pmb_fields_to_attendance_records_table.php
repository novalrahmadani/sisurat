<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('registration_number', 100)->nullable();
            $table->string('study_program', 150)->nullable();
            $table->string('position', 150)->nullable()->change();
            $table->string('institution', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attendance_records')->whereNull('position')->update(['position' => '']);
        DB::table('attendance_records')->whereNull('institution')->update(['institution' => '']);

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['registration_number', 'study_program']);
            $table->string('position', 150)->nullable(false)->change();
            $table->string('institution', 255)->nullable(false)->change();
        });
    }
};
