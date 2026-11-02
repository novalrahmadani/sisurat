<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->string('letter_number', 180)->unique();
            $table->string('recipient_position', 150)->nullable();
            $table->string('recipient_name', 150)->nullable();
            $table->string('institution_type', 32)->nullable();
            $table->string('institution_name')->nullable();
            $table->string('target_class', 100)->nullable();
            $table->string('activity_destination')->nullable();
            $table->timestamps();

            $table->index(['letter_batch_id', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
