<?php

use App\Models\QuranAssessment;
use App\Models\QuranExam;
use App\Models\User;
use App\Support\QuranAssessmentPdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
});

function reportMqRow(QuranExam $exam, int $number, array $extra = []): QuranAssessment
{
    return $exam->assessments()->create([
        'name' => 'Peserta '.sprintf('%03d', $number), 'registration_number' => sprintf('%06d', $number),
        'study_program' => 'Pendidikan Bahasa dan Sastra Indonesia', 'assessment_date' => '2026-10-29',
        'examiner_name' => 'Dr. Penguji Contoh, M.Pd.', 'reading_reference' => 'Al-Fatihah 1-7',
        'fasohah_score' => 70, 'tajwid_score' => 80, 'makhraj_score' => 90, 'fluency_score' => 80, 'result' => 'mq2',
        'created_by' => $exam->created_by, 'version' => (string) Str::uuid(), 'submission_token' => (string) Str::uuid(), ...$extra,
    ]);
}

test('MQ recap PDFs require an authenticated account', function () {
    $this->get(route('quran-tests.pdf'))->assertRedirect(route('login'));
});

test('a PDF recap includes every matching participant beyond the list pagination', function () {
    $exam = QuranExam::factory()->create(['title' => 'Tes MQ Contoh untuk Pemeriksaan']);
    foreach (range(1, 67) as $number) {
        reportMqRow($exam, $number);
    }
    $response = $this->actingAs($exam->creator)->get(route('quran-tests.pdf', ['exam_id' => $exam->id, 'page' => 2]));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload('rekap-tes-baca-alquran.pdf');
    $pdf = $response->getContent();
    expect($pdf)->toStartWith('%PDF-')->and(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBeGreaterThan(1);
    if ($path = getenv('SISURAT_QURAN_PREVIEW_PDF')) {
        file_put_contents($path, $pdf);
    }
});

test('PDF filters keep the owner activity search and category scope while ignoring page size', function () {
    $exam = QuranExam::factory()->create();
    $expected = reportMqRow($exam, 1);
    reportMqRow($exam, 2, ['result' => 'mq1']);
    reportMqRow(QuranExam::factory()->create(['created_by' => $exam->created_by]), 1);
    reportMqRow(QuranExam::factory()->create(), 1);
    $this->mock(QuranAssessmentPdf::class)->shouldReceive('render')->once()->withArgs(fn (Collection $rows, string $scope, array $filters) => $rows->modelKeys() === [$expected->id] && $filters['result'] === 'mq2' && $filters['q'] === 'Peserta 001')->andReturn('%PDF-test');
    $this->actingAs($exam->creator)->get(route('quran-tests.pdf', ['exam_id' => $exam->id, 'q' => 'Peserta 001', 'result' => 'mq2', 'page' => 3]))->assertOk();
});

test('another owners activity cannot be selected in PDF or CSV filters', function () {
    $exam = QuranExam::factory()->create();
    $other = User::factory()->create(['is_active' => true]);
    foreach (['quran-tests.pdf', 'quran-tests.export', 'quran-tests.index'] as $route) {
        $this->actingAs($other)->get(route($route, ['exam_id' => $exam->id]))->assertSessionHasErrors('exam_id');
    }
});

test('the PDF table displays all criteria classification and safely escapes free text', function () {
    $exam = QuranExam::factory()->create();
    $row = reportMqRow($exam, 1, ['name' => '<script>alert(1)</script>']);
    $html = view('quran-tests.report', ['rows' => new Collection([$row]), 'scope' => 'Contoh', 'filters' => [], 'exportedAt' => now('Asia/Jakarta'), 'letterhead' => 'data:image/png;base64,'])->render();
    expect($html)->toContain('Fasohah', 'Tajwid', 'Makhroj', 'Kelancaran', 'Rata-rata', 'MQ 2', '80,00', '&lt;script&gt;')->not->toContain('<script>alert(1)</script>');
});

test('an empty MQ recap remains a readable single page PDF', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $response = $this->actingAs($owner)->get(route('quran-tests.pdf'));
    $response->assertOk();
    expect(preg_match_all('/\/Type\s*\/Page\b/',$response->getContent()))->toBe(1);
});
