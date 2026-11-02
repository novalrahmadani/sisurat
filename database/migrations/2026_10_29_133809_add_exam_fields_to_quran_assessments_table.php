<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_assessments', function (Blueprint $table): void {
            $table->foreignId('quran_exam_id')->nullable()->constrained('quran_exams')->nullOnDelete();
            $table->string('registration_key', 64)->nullable();
            $table->unique(['quran_exam_id', 'registration_key'], 'quran_exam_registration_unique');
        });
    }

    public function down(): void
    {
        Schema::table('quran_assessments', function (Blueprint $table): void {
            $table->dropUnique('quran_exam_registration_unique');
            $table->dropConstrainedForeignId('quran_exam_id');
            $table->dropColumn('registration_key');
        });
    }
};
