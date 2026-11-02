<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('registration_number', 100);
            $table->string('study_program', 150);
            $table->date('assessment_date');
            $table->string('examiner_name', 150)->nullable();
            $table->string('reading_reference', 255)->nullable();
            $table->unsignedTinyInteger('makhraj_score')->nullable();
            $table->unsignedTinyInteger('tajwid_score')->nullable();
            $table->unsignedTinyInteger('fluency_score')->nullable();
            $table->string('result', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('version');
            $table->uuid('submission_token');
            $table->timestamps();
            $table->unique(['created_by', 'submission_token'], 'quran_assessments_creator_token_unique');
            $table->index(['created_by', 'assessment_date', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_assessments');
    }
};
