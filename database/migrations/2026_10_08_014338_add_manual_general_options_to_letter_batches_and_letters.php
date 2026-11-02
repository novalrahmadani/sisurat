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
        if (! Schema::hasColumn('letter_batches', 'general_options')) {
            Schema::table('letter_batches', function (Blueprint $table) {
                $table->json('general_options')->nullable();
            });
        }

        if (! Schema::hasColumn('letters', 'recipient_salutation')) {
            Schema::table('letters', function (Blueprint $table) {
                $table->string('recipient_salutation', 150)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('letters', 'recipient_salutation')) {
            Schema::table('letters', function (Blueprint $table) {
                $table->dropColumn('recipient_salutation');
            });
        }

        if (Schema::hasColumn('letter_batches', 'general_options')) {
            Schema::table('letter_batches', function (Blueprint $table) {
                $table->dropColumn('general_options');
            });
        }
    }
};
