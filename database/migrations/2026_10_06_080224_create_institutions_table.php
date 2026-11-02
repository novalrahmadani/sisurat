<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institutions')) {
            Schema::create('institutions', function (Blueprint $table) {
                $table->id();
                $table->string('institution_type', 32);
                $table->string('name');
                $table->string('district', 150);
                $table->string('address', 500)->nullable();
                $table->unsignedInteger('source_number')->nullable();
                $table->string('seed_key', 64)->nullable()->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('letters', 'institution_id')) {
            Schema::table('letters', function (Blueprint $table) {
                $table->foreignId('institution_id')->nullable();
            });
        }

        $hasInstitutionForeignKey = collect(Schema::getForeignKeys('letters'))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['institution_id']);

        if (! $hasInstitutionForeignKey) {
            Schema::table('letters', function (Blueprint $table) {
                $table->foreign('institution_id')->references('id')->on('institutions')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('letters', 'recipient_address')) {
            Schema::table('letters', function (Blueprint $table) {
                $table->string('recipient_address', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn('recipient_address');
        });

        Schema::dropIfExists('institutions');
    }
};
