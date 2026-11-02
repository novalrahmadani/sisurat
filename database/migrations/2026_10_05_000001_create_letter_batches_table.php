<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->date('letter_date');
            $table->unsignedInteger('first_sequence');
            $table->string('number_prefix', 120);
            $table->date('event_start')->nullable();
            $table->date('event_end')->nullable();
            $table->string('signatory_title', 150);
            $table->string('signatory_name', 150);
            $table->string('signatory_number', 100)->nullable();
            $table->timestamps();

            $table->index(['type', 'letter_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_batches');
    }
};
