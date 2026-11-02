<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interview_submissions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('wave')->nullable();
            $table->string('period', 100)->nullable();
            $table->string('campus', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('interview_submissions', function (Blueprint $table): void {
            $table->dropColumn(['wave', 'period', 'campus']);
        });
    }
};
