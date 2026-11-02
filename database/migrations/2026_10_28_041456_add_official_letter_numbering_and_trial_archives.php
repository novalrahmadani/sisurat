<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('letter_numberings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('next_sequence')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('configured_at')->nullable();
            $table->timestamps();
        });
        Schema::table('letter_batches', function (Blueprint $table) {
            $table->boolean('is_trial')->default(false);
        });
        Schema::table('letters', function (Blueprint $table) {
            $table->string('numbering_scope', 32)->default('official');
            $table->dropUnique('letters_letter_number_unique');
            $table->unique(['numbering_scope', 'letter_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('letters')->select('letter_number')->groupBy('letter_number')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Arsip uji coba dan surat resmi memiliki nomor yang sama. Pertahankan skema ini agar seluruh arsip tetap tersimpan.');
        }
        Schema::table('letters', function (Blueprint $table) {
            $table->dropUnique(['numbering_scope', 'letter_number']);
            $table->dropColumn('numbering_scope');
            $table->unique('letter_number');
        });
        Schema::table('letter_batches', fn (Blueprint $table) => $table->dropColumn('is_trial'));
        Schema::dropIfExists('letter_numberings');
    }
};
