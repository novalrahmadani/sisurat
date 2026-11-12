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
        Schema::create('pmb_signatories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('title', 150);
            $table->string('number', 100)->nullable();
            $table->string('signature_path')->nullable();
            $table->string('stamp_path')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        $legacyId = DB::table('pmb_signatories')->insertGetId([
            'name' => 'Wardianto, M.Kom', 'title' => 'Ketua Pusat PMB', 'number' => 'NIP.Y 19951010202310.1.580',
            'signature_path' => 'images/pmb-signature.png', 'stamp_path' => 'images/pmb-stamp.png',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['letter_batches', 'attendance_events'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('pmb_signatory_id')->nullable()->constrained('pmb_signatories')->restrictOnDelete();
            });
        }
        DB::table('letter_batches')->update(['pmb_signatory_id' => $legacyId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['letter_batches', 'attendance_events'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('pmb_signatory_id'));
        }
        Schema::dropIfExists('pmb_signatories');
    }
};
