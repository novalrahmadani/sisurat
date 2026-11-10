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
        Schema::create('pmb_prospect_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('study_program', 150);
            $table->string('school_name', 200);
            $table->string('school_district', 150);
            $table->string('school_address', 500);
            $table->string('affiliate_name', 150);
            $table->uuid('submission_token')->unique();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['submitted_at', 'id']);
            $table->index(['school_district', 'school_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmb_prospect_submissions');
    }
};

