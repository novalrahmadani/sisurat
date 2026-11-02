<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_forms', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('questions');
            $table->uuid('questions_version');
            $table->boolean('is_open')->default(true);
            $table->string('public_token', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['created_by', 'is_open', 'id']);
        });

        Schema::create('interview_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_form_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('registration_number', 100);
            $table->string('registration_key', 64);
            $table->string('study_program', 150);
            $table->json('answers');
            $table->uuid('submission_token');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['interview_form_id', 'submission_token'], 'interview_submissions_form_token_unique');
            $table->unique(['interview_form_id', 'registration_key'], 'interview_submissions_form_registration_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_submissions');
        Schema::dropIfExists('interview_forms');
    }
};
