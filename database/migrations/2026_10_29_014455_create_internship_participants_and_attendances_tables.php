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
        Schema::create('internship_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('student_number', 100)->unique();
            $table->string('institution', 200);
            $table->string('study_program', 150)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->string('access_code_hash')->nullable();
            $table->timestamps();
        });
        Schema::create('internship_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('internship_participant_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date')->index();
            $table->dateTime('checked_in_at');
            $table->dateTime('checked_out_at')->nullable();
            $table->mediumText('check_in_signature');
            $table->mediumText('check_out_signature')->nullable();
            $table->string('check_in_note', 500)->nullable();
            $table->string('check_out_note', 500)->nullable();
            $table->timestamps();
            $table->unique(['internship_participant_id', 'attendance_date'], 'internship_participant_day_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internship_attendances');
        Schema::dropIfExists('internship_participants');
    }
};
