<?php

namespace App\Support;

use App\Models\QuranAssessment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;

class QuranAssessmentPdf
{
    /**
     * @param  Collection<int, QuranAssessment>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function render(Collection $rows, string $scope, array $filters): string
    {
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);
        $options = new Options;
        $options->setDefaultFont('DejaVu Serif');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(public_path());
        $options->setTempDir($cache);
        $options->setFontCache($cache);
        $options->setIsFontSubsettingEnabled(true);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml(view('quran-tests.report', [
            'rows' => $rows, 'scope' => $scope, 'filters' => $filters, 'exportedAt' => now('Asia/Jakarta'),
            'letterhead' => 'data:image/png;base64,'.base64_encode(File::get(public_path('images/universitas-nurul-huda-letterhead.png'))),
        ])->render(), 'UTF-8');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Serif');
        $canvas->page_text(42, $canvas->get_height() - 25, 'PMB Universitas Nurul Huda', $font, 8, [.3, .35, .32]);
        $canvas->page_text($canvas->get_width() - 150, $canvas->get_height() - 25, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [.3, .35, .32]);

        return $pdf->output();
    }
}
