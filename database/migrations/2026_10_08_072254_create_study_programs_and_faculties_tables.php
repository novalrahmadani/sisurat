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
        foreach (['study_programs', 'faculties'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) {
                    $table->id();
                    $table->string('name', 150);
                    $table->char('identity_key', 64)->unique();
                    $table->timestamps();
                });
            }
        }

        $categories = [
            'study_programs' => [
                'Informatika',
                'Matematika',
                'Sains Pertanian',
                'Pendidikan Agama Islam',
                'Pendidikan Guru Madrasah Ibtidaiyah',
                'Pendidikan Fisika',
                'Pendidikan Bahasa dan Sastra Indonesia',
                'Pendidikan Bahasa Inggris',
                'Pendidikan Ekonomi',
                'Pendidikan Teknologi Informasi',
            ],
            'faculties' => [
                'Fakultas Sains dan Teknologi',
                'Fakultas Ilmu Pendidikan',
                'Fakultas Agama Islam',
            ],
        ];
        $timestamp = now();

        foreach ($categories as $tableName => $names) {
            foreach ($names as $name) {
                $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
                $identityKey = hash('sha256', mb_strtolower($name, 'UTF-8'));
                if (! DB::table($tableName)->where('identity_key', $identityKey)->exists()) {
                    DB::table($tableName)->insertOrIgnore([
                        'name' => $name,
                        'identity_key' => $identityKey,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculties');
        Schema::dropIfExists('study_programs');
    }
};
