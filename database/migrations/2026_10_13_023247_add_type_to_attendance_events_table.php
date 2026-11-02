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
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->string('type', 20)->default('meeting');
            $table->index(['created_by', 'type', 'event_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropIndex(['created_by', 'type', 'event_date']);
            $table->dropColumn('type');
        });
    }
};
