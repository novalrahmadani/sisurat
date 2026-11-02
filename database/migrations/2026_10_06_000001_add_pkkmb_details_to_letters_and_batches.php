<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_batches', function (Blueprint $table) {
            $table->string('event_location')->nullable();
            $table->string('academic_year', 30)->nullable();
        });

        Schema::table('letters', function (Blueprint $table) {
            $table->string('participant_name', 150)->nullable();
            $table->string('participant_nim', 50)->nullable();
            $table->string('participant_study_program', 150)->nullable();
            $table->string('participation_status', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn(['participant_name', 'participant_nim', 'participant_study_program', 'participation_status']);
        });

        Schema::table('letter_batches', function (Blueprint $table) {
            $table->dropColumn(['event_location', 'academic_year']);
        });
    }
};
