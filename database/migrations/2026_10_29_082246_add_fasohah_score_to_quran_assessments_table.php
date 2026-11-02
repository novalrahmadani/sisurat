<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_assessments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('fasohah_score')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quran_assessments', function (Blueprint $table): void {
            $table->dropColumn('fasohah_score');
        });
    }
};
