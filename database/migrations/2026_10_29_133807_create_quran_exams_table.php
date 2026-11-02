<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_exams', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->date('assessment_date');
            $table->boolean('is_open')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('public_token', 64)->unique();
            $table->uuid('version');
            $table->timestamps();
            $table->index(['created_by', 'assessment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_exams');
    }
};
