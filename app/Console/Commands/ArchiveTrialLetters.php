<?php

namespace App\Console\Commands;

use App\Models\Letter;
use App\Models\LetterBatch;
use App\Models\LetterNumbering;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('letters:archive-trial {--year= : Tahun uji coba yang telah dikonfirmasi} {--through-batch= : ID batch terakhir yang telah dikonfirmasi} {--apply : Terapkan pelabelan}')]
#[Description('Pisahkan arsip uji coba yang telah dikonfirmasi dari penomoran resmi tanpa menghapus surat')]
class ArchiveTrialLetters extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = filter_var($this->option('year'), FILTER_VALIDATE_INT);
        $through = filter_var($this->option('through-batch'), FILTER_VALIDATE_INT);
        if ($year === false || $year < 2000 || $year > 2100 || $through === false || $through < 1) {
            $this->error('Isi --year dan --through-batch sesuai arsip uji coba yang telah dikonfirmasi.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $count = LetterBatch::withTrashed()->whereYear('letter_date', $year)->where('id', '<=', $through)->where('is_trial', false)->count();
            $this->info($count.' batch akan ditandai sebagai uji coba. Belum ada arsip yang diubah.');

            return self::SUCCESS;
        }
        $count = DB::transaction(function () use ($year, $through): int {
            $numbering = LetterNumbering::lockForYear($year);
            $batches = LetterBatch::withTrashed()->whereYear('letter_date', $year)->where('id', '<=', $through)->where('is_trial', false)->orderBy('id')->lockForUpdate()->get();
            foreach ($batches as $batch) {
                Letter::withTrashed()->where('letter_batch_id', $batch->id)->update(['numbering_scope' => 'trial-'.$batch->id]);
                $batch->forceFill(['is_trial' => true])->save();
            }
            if ($numbering->configured_at === null) {
                $numbering->update(['next_sequence' => Letter::lastOfficialSequenceForYear($year) + 1]);
            }

            return $batches->count();
        }, 5);
        $this->info($count.' batch ditandai sebagai uji coba. Seluruh surat tetap tersimpan.');

        return self::SUCCESS;
    }
}
