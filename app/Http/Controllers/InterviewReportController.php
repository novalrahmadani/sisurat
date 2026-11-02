<?php

namespace App\Http\Controllers;

use App\Actions\Interviews\InterviewAnswerTable;
use App\Actions\Interviews\InterviewReport;
use App\Models\InterviewForm;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InterviewReportController extends Controller
{
    public function csv(InterviewForm $interview): StreamedResponse
    {
        Gate::authorize('view', $interview);
        $report = new InterviewReport($interview);
        $questions = $report->questions();
        $filename = 'hasil-wawancara-'.(Str::slug($interview->title) ?: $interview->id).'.csv';

        return response()->streamDownload(function () use ($report, $questions, $interview): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Berkas CSV belum dapat dibuat. Silakan coba kembali.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            $header = ['No', 'Nama Peserta', 'Nomor Registrasi', 'Program Studi', 'Waktu Kirim (WIB)', 'Jumlah Pertanyaan', 'Jumlah Terjawab'];
            $questionNumber = 0;

            foreach ($questions as $question) {
                $questionNumber++;
                $header[] = 'P'.$questionNumber.'. '.$question['prompt'].' ('.($question['required'] ? 'Wajib' : 'Opsional').')';
            }

            $includeSession = $interview->wave !== null || $interview->period !== null || $interview->campus !== null
                || $interview->submissions()->whereNotNull('wave')->exists()
                || $interview->submissions()->whereNotNull('academic_year')->exists();
            if ($includeSession) {
                $header = [...$header, 'Gelombang', 'Periode', 'Tahun Akademik', 'Lokasi Kampus'];
            }
            fputcsv($output, $header, ',', '"', '');
            $participantNumber = 0;

            foreach ($report->submissions() as $submission) {
                $participantNumber++;
                $answers = $report->answersByQuestion($submission);
                $row = [
                    $participantNumber,
                    $this->csvCell($submission->name),
                    "'".$submission->registration_number,
                    $this->csvCell($submission->study_program),
                    $submission->submitted_at->timezone('Asia/Jakarta')->format('d-m-Y H:i:s'),
                    count($submission->answers),
                    count(array_filter($answers, fn (string $answer): bool => trim($answer) !== '')),
                ];

                foreach ($questions as $key => $question) {
                    $row[] = array_key_exists($key, $answers)
                        ? $this->csvCell(trim($answers[$key]) === '' ? 'Tidak diisi' : $answers[$key])
                        : 'Belum ditanyakan';
                }

                if ($includeSession) {
                    $session = $submission->testSessionDetails();
                    $row = [...$row, $session['wave'] === null ? '' : 'Gelombang '.$session['wave'],
                        $this->csvCell($session['period'] ?? ''), $this->csvCell($session['academic_year'] ?? ''), $session['campus'] === null ? '' : 'Kampus '.$session['campus']];
                }
                fputcsv($output, $row, ',', '"', '');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function pdf(Request $request, InterviewForm $interview): Response
    {
        Gate::authorize('view', $interview);
        if ($request->hasHeader('X-Inertia')) {
            return Inertia::location(route('interviews.pdf', $interview));
        }
        $report = new InterviewReport($interview);
        $cachePath = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cachePath);

        $options = new Options;
        $options->setDefaultFont('DejaVu Serif');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsFontSubsettingEnabled(true);
        $options->setChroot(public_path());
        $options->setTempDir($cachePath);
        $options->setFontCache($cachePath);

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml(view('interviews.report', [
            'interview' => $interview,
            'submissions' => $report->submissions()->collect(),
            'exportedAt' => now('Asia/Jakarta'),
            'answerTable' => new InterviewAnswerTable($pdf->getFontMetrics(), questionWidth: 160, answerWidth: 224),
            'letterhead' => 'data:image/png;base64,'.base64_encode(File::get(public_path('images/universitas-nurul-huda-letterhead.png'))),
        ])->render(), 'UTF-8');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Serif');
        $canvas->page_text(45, $canvas->get_height() - 29, 'PMB Universitas Nurul Huda', $font, 8, [0.3, 0.35, 0.32]);
        $canvas->page_text($canvas->get_width() - 142, $canvas->get_height() - 29, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.3, 0.35, 0.32]);
        $filename = 'hasil-wawancara-'.(Str::slug($interview->title) ?: $interview->id).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $filename),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F\x{FEFF}\x{200B}]*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }
}
