<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_outgoing_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('recipient', 500);
            $table->date('letter_date')->index();
            $table->string('subject', 500);
            $table->string('letter_number', 200);
            $table->unsignedInteger('sequence_number');
            $table->unsignedSmallInteger('letter_year');
            $table->string('type', 20)->default('general');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['letter_year', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_outgoing_letters');
    }
};
