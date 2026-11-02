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
        Schema::table('internship_participants', function (Blueprint $table): void {
            $table->string('participant_category', 20)->nullable();
            $table->string('class_level', 50)->nullable();
            $table->string('registration_source', 20)->default('admin');
            $table->string('device_token_hash', 64)->nullable();
            $table->dateTime('device_expires_at')->nullable();
            $table->string('recovery_token_hash', 64)->nullable();
            $table->dateTime('recovery_expires_at')->nullable();
            $table->dropUnique('internship_participants_student_number_unique');
            $table->unique(['participant_category', 'institution', 'student_number'], 'internship_participant_identity_unique');
        });
        Schema::create('internship_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->default(100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('internship_participants')->select('student_number')->groupBy('student_number')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Pemulihan skema lama membutuhkan peninjauan identitas peserta yang sama pada institusi berbeda. Data peserta tidak diubah.');
        }
        Schema::dropIfExists('internship_settings');
        Schema::table('internship_participants', function (Blueprint $table): void {
            $table->dropUnique('internship_participant_identity_unique');
            $table->dropColumn(['participant_category', 'class_level', 'registration_source', 'device_token_hash', 'device_expires_at', 'recovery_token_hash', 'recovery_expires_at']);
            $table->unique('student_number');
        });
    }
};
