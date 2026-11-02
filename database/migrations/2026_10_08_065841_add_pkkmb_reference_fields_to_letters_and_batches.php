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
        if (! Schema::hasColumn('letter_batches', 'pkkmb_options')) {
            Schema::table('letter_batches', function (Blueprint $table) {
                $table->json('pkkmb_options')->nullable();
            });
        }

        if (! Schema::hasColumn('letters', 'participant_faculty')) {
            Schema::table('letters', function (Blueprint $table) {
                $table->string('participant_faculty', 150)->nullable();
            });
        }

        if (! Schema::hasColumn('letters', 'participant_result')) {
            Schema::table('letters', function (Blueprint $table) {
                $table->string('participant_result', 30)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('letters', 'participant_result')) {
            Schema::table('letters', function (Blueprint $table) {
                $table->dropColumn('participant_result');
            });
        }

        if (Schema::hasColumn('letters', 'participant_faculty')) {
            Schema::table('letters', function (Blueprint $table) {
                $table->dropColumn('participant_faculty');
            });
        }

        if (Schema::hasColumn('letter_batches', 'pkkmb_options')) {
            Schema::table('letter_batches', function (Blueprint $table) {
                $table->dropColumn('pkkmb_options');
            });
        }
    }
};
