<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['quran_assessments', 'interview_submissions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('academic_year', 9)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['quran_assessments', 'interview_submissions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('academic_year');
            });
        }
    }
};
