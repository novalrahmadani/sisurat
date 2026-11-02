<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('period_year');
            $table->boolean('is_open')->default(false);
            $table->json('questions');
            $table->uuid('version');
            $table->char('public_token', 64)->unique();
            $table->uuid('submission_token');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['created_by', 'submission_token']);
            $table->index(['created_by', 'period_year', 'id']);
        });
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('registration_number', 100);
            $table->char('registration_key', 64);
            $table->string('study_program', 150);
            $table->string('school_origin', 200)->nullable();
            $table->string('survey_title', 180);
            $table->unsignedSmallInteger('period_year');
            $table->json('answers');
            $table->uuid('submission_token');
            $table->string('consent_text', 500);
            $table->timestamp('consented_at');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['survey_id', 'registration_key']);
            $table->unique(['survey_id', 'submission_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('surveys');
    }
};
