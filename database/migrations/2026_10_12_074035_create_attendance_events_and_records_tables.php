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
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->date('event_date');
            $table->string('location', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_open')->default(true);
            $table->string('public_token', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['event_date', 'id']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_event_id')->constrained('attendance_events')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('position', 150);
            $table->string('institution', 255);
            $table->mediumText('signature_png');
            $table->uuid('submission_token');
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->unique(['attendance_event_id', 'submission_token'], 'attendance_records_submission_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_events');
    }
};
