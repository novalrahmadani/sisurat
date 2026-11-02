<?php

use App\Actions\Interviews\InterviewAnswerTable;
use App\Models\InterviewForm;
use App\Models\InterviewSubmission;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function interviewReportForm(User $owner, ?array $questions = null): InterviewForm
{
    return InterviewForm::query()->create([
        'title' => 'Wawancara Gelombang 1',
        'questions' => $questions ?? [
            ['id' => '00000000-0000-4000-8000-000000000001', 'prompt' => 'Mengapa memilih Universitas Nurul Huda?', 'required' => true],
            ['id' => '00000000-0000-4000-8000-000000000002', 'prompt' => 'Apa harapan Anda?', 'required' => false],
        ],
        'questions_version' => (string) Str::uuid(),
        'public_token' => Str::random(64),
        'is_open' => true,
        'created_by' => $owner->id,
    ]);
}

function interviewReportParticipant(InterviewForm $form, int $number, ?array $answers = null, array $attributes = []): InterviewSubmission
{
    $registration = str_pad((string) $number, 8, '0', STR_PAD_LEFT);

    return $form->submissions()->create([
        'name' => 'Peserta '.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
        'registration_number' => $registration,
        'registration_key' => InterviewSubmission::registrationKey($registration),
        'study_program' => 'Pendidikan Bahasa dan Sastra Indonesia',
        'submitted_at' => Carbon::parse('2026-10-28 01:25:00', 'UTC'),
        'submission_token' => (string) Str::uuid(),
        'answers' => $answers ?? array_map(fn (array $question): array => [
            'question_id' => $question['id'],
            'question' => $question['prompt'],
            'required' => $question['required'],
            'answer' => 'Jawaban '.$number.' - '.$question['prompt'],
        ], $form->questions),
        ...$attributes,
    ]);
}

function interviewReportCsvRows(string $content): array
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content);
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);

    return $rows;
}

test('CSV includes one complete row per participant beyond page and chunk boundaries', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $form = interviewReportForm($owner);
    for ($number = 1; $number <= 121; $number++) {
        interviewReportParticipant($form, $number);
    }

    $response = $this->actingAs($owner)->get(route('interviews.export', ['interview' => $form, 'q' => 'Peserta 001', 'page' => 2]));

    $response->assertOk()->assertDownload('hasil-wawancara-wawancara-gelombang-1.csv');
    $rows = interviewReportCsvRows($response->streamedContent());
    expect($rows)->toHaveCount(122);
    expect($rows[0])->toHaveCount(9)->toContain('P1. Mengapa memilih Universitas Nurul Huda? (Wajib)', 'P2. Apa harapan Anda? (Opsional)');
    expect($rows[1])->toBe(['1', 'Peserta 001', "'00000001", 'Pendidikan Bahasa dan Sastra Indonesia', '28-10-2026 08:25:00', '2', '2', 'Jawaban 1 - Mengapa memilih Universitas Nurul Huda?', 'Jawaban 1 - Apa harapan Anda?']);
    expect($rows[121][1])->toBe('Peserta 121');
    expect($rows[121][8])->toBe('Jawaban 121 - Apa harapan Anda?');
});

test('CSV retains historical questions, optional blanks, multiline text and participants without answers', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $form = interviewReportForm($owner);
    $oldAnswers = [
        ['question_id' => $form->questions[0]['id'], 'question' => 'Pertanyaan lama, sebelum diperbarui?', 'required' => true, 'answer' => "Saya ingin belajar.\nBaris kedua, lengkap dengan koma."],
        ['question_id' => $form->questions[1]['id'], 'question' => $form->questions[1]['prompt'], 'required' => false, 'answer' => ''],
    ];
    interviewReportParticipant($form, 1, $oldAnswers);
    interviewReportParticipant($form, 2, []);

    $response = $this->actingAs($owner)->get(route('interviews.export', $form));

    $response->assertOk();
    $rows = interviewReportCsvRows($response->streamedContent());
    expect($rows)->toHaveCount(3);
    expect($rows[0][9])->toBe('P3. Pertanyaan lama, sebelum diperbarui? (Wajib)');
    expect($rows[1][7])->toBe('Belum ditanyakan');
    expect($rows[1][8])->toBe('Tidak diisi');
    expect($rows[1][9])->toBe("Saya ingin belajar.\nBaris kedua, lengkap dengan koma.");
    expect($rows[2][1])->toBe('Peserta 002');
    expect($rows[2][5])->toBe('0');
});

test('CSV treats spreadsheet formulas and registration numbers as text', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $form = interviewReportForm($owner);
    interviewReportParticipant($form, 1, [
        ['question_id' => $form->questions[0]['id'], 'question' => $form->questions[0]['prompt'], 'required' => true, 'answer' => "\t=HYPERLINK(\"https://example.test\")"],
    ], ['name' => '=SUM(1,1)', 'study_program' => '@program', 'registration_number' => '00001234']);

    $response = $this->actingAs($owner)->get(route('interviews.export', $form));

    $response->assertOk();
    $rows = interviewReportCsvRows($response->streamedContent());
    expect($rows[1][1])->toBe("'=SUM(1,1)");
    expect($rows[1][2])->toBe("'00001234");
    expect($rows[1][3])->toBe("'@program");
    expect($rows[1][7])->toStartWith("'\t=");
});

test('PDF downloads include every participant and allow long answers to continue across pages', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $form = interviewReportForm($owner);
    for ($number = 1; $number <= 21; $number++) {
        $answers = null;
        if ($number === 1) {
            $answers = [
                ['question_id' => $form->questions[0]['id'], 'question' => $form->questions[0]['prompt'], 'required' => true, 'answer' => str_repeat('Saya ingin belajar, mengembangkan kemampuan, dan berkontribusi bagi masyarakat. ', 60).'AKHIR JAWABAN PANJANG'],
                ['question_id' => $form->questions[1]['id'], 'question' => $form->questions[1]['prompt'], 'required' => false, 'answer' => ''],
            ];
        }
        interviewReportParticipant($form, $number, $answers);
    }
    $this->travelTo(Carbon::parse('2026-10-28 08:36:00', 'Asia/Jakarta'));

    $response = $this->actingAs($owner)->get(route('interviews.pdf', ['interview' => $form, 'q' => 'Peserta 001', 'page' => 2]));

    $response->assertOk()->assertDownload('hasil-wawancara-wawancara-gelombang-1.pdf')->assertHeader('Content-Type', 'application/pdf');
    $pdf = $response->getContent();
    expect($pdf)->toStartWith('%PDF-');
    expect(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBeGreaterThanOrEqual(2);
    if ($previewPath = getenv('SISURAT_INTERVIEW_PREVIEW_PDF')) {
        file_put_contents($previewPath, $pdf);
    }
});

test('PDF report markup includes all identities and historical answers while escaping free text', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $form = interviewReportForm($owner);
    interviewReportParticipant($form, 1, [
        ['question_id' => $form->questions[0]['id'], 'question' => 'Pertanyaan lama <script>danger()</script>', 'required' => true, 'answer' => "Jawaban lengkap <img src='https://example.test/x'>\nBaris terakhir."],
    ], ['name' => 'Nama <script>danger()</script>']);
    interviewReportParticipant($form, 2);

    $html = view('interviews.report', [
        'interview' => $form,
        'submissions' => $form->submissions()->orderBy('id')->get(),
        'exportedAt' => Carbon::parse('2026-10-28 08:36:00', 'Asia/Jakarta'),
        'answerTable' => new InterviewAnswerTable((new Dompdf)->getFontMetrics()),
        'letterhead' => 'data:image/png;base64,',
    ])->render();

    expect(preg_replace('/\s+/u', ' ', $html))->toContain('Peserta 002', '00000002', 'Pertanyaan lama &lt;script&gt;', 'Baris terakhir.', '2 peserta', 'REKAP PERTANYAAN DAN JAWABAN SELURUH PESERTA')
        ->not->toContain('<script>danger()</script>', "<img src='https://example.test/x'>");

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $headers = (new DOMXPath($document))->query('(//table[@class="qa-table"])[1]/thead/tr/th');
    expect(array_map(fn (DOMNode $node): string => trim($node->textContent), iterator_to_array($headers)))->toBe(['No.', 'Nama Peserta', 'Nomor Registrasi', 'Program Studi', 'Pertanyaan', 'Jawaban']);
    expect($document->getElementsByTagName('table')->length)->toBe(1);
    expect($html)->not->toContain('Dikirim (WIB)', '<th>Terjawab</th>', 'RINCIAN PESERTA');
});

test('question and answer table continuation rows retain very long and multiline content', function () {
    $table = new InterviewAnswerTable((new Dompdf)->getFontMetrics());
    $question = str_repeat('W', 1000);
    $answer = "Baris pertama.\n".str_repeat('Jawaban lengkap berbahasa Indonesia. ', 130).'PENUTUP JAWABAN';

    $rows = $table->rows([['question_id' => 'question-1', 'question' => $question, 'answer' => $answer, 'required' => true]]);

    expect(count($rows))->toBeGreaterThan(1);
    expect(preg_replace('/\s+/u', '', implode('', array_column($rows, 'question'))))->toBe($question);
    expect(preg_replace('/\s+/u', '', implode('', array_column($rows, 'answer'))))->toBe(preg_replace('/\s+/u', '', $answer));
    expect(array_unique(array_column($rows, 'number')))->toBe([1]);
    expect($rows[0]['continuation'])->toBeFalse();
    expect($rows[1]['continuation'])->toBeTrue();
});

test('empty CSV exports still include headers and available questions', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $form = interviewReportForm($owner);

    $response = $this->actingAs($owner)->get(route('interviews.export', $form));

    $response->assertOk();
    expect(interviewReportCsvRows($response->streamedContent()))->toHaveCount(1);
});

test('an empty form can download a valid PDF with no participant details', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $form = interviewReportForm($owner);

    $response = $this->actingAs($owner)->get(route('interviews.pdf', $form));

    $response->assertOk()->assertDownload('hasil-wawancara-wawancara-gelombang-1.pdf');
    expect($response->getContent())->toStartWith('%PDF-');
    expect(preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()))->toBe(1);
});

test('guests cannot export interview results', function (string $routeName) {
    $owner = User::factory()->create(['is_active' => true]);
    $form = interviewReportForm($owner);

    $response = $this->get(route($routeName, $form));

    $response->assertRedirect(route('login'));
})->with(['interviews.export', 'interviews.pdf']);

test('other accounts cannot export another owners interview results', function (string $routeName) {
    $owner = User::factory()->create(['is_active' => true]);
    $other = User::factory()->create(['is_active' => true]);
    $form = interviewReportForm($owner);

    $response = $this->actingAs($other)->get(route($routeName, $form));

    $response->assertForbidden();
})->with(['interviews.export', 'interviews.pdf']);
