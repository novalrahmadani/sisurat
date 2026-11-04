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
        Schema::table('internship_participants', function (Blueprint $table) {
            $table->char('pin_lookup_hash', 64)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internship_participants', function (Blueprint $table) {
            $table->dropUnique(['pin_lookup_hash']);
            $table->dropColumn('pin_lookup_hash');
        });
    }
};
