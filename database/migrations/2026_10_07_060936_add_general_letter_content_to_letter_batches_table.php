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
        $missing = array_values(array_filter(
            ['subject', 'attachment', 'body'],
            fn (string $column): bool => ! Schema::hasColumn('letter_batches', $column),
        ));

        if ($missing === []) {
            return;
        }

        Schema::table('letter_batches', function (Blueprint $table) use ($missing) {
            if (in_array('subject', $missing, true)) {
                $table->string('subject', 255)->nullable();
            }
            if (in_array('attachment', $missing, true)) {
                $table->string('attachment', 120)->nullable();
            }
            if (in_array('body', $missing, true)) {
                $table->longText('body')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letter_batches', function (Blueprint $table) {
            $table->dropColumn(['subject', 'attachment', 'body']);
        });
    }
};
