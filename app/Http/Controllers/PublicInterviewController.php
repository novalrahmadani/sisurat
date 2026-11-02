<?php

namespace App\Http\Controllers;

use App\Http\Requests\InterviewSubmissionRequest;
use App\Models\InterviewForm;
use App\Models\InterviewSubmission;
use App\Models\StudyProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicInterviewController extends Controller
{
    public function join(Request $request, string $token): Response
    {
        $interview = InterviewForm::query()->where('public_token', $token)->firstOrFail();
        $response = Inertia::render('interviews/Join', [
            'interview' => [
                ...$interview->only(['wave', 'period', 'campus']),
                'title' => $interview->title,
                'description' => $interview->description,
                'is_open' => $interview->is_open,
                'questions' => $interview->is_open ? $interview->questions : [],
                'questions_version' => $interview->questions_version,
            ],
            'token' => $token,
            'studyPrograms' => $interview->is_open ? StudyProgram::query()->orderBy('name')->get(['id', 'name']) : [],
            'submitted' => (bool) $request->session()->get('interviews.receipts.'.$interview->id, false),
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }

    public function submit(InterviewSubmissionRequest $request, string $token): RedirectResponse
    {
        $data = $request->validated();
        $interview = DB::transaction(function () use ($data, $token): InterviewForm {
            $interview = InterviewForm::query()->where('public_token', $token)->lockForUpdate()->firstOrFail();
            if ($interview->submissions()->where('submission_token', $data['submission_token'])->exists()) {
                return $interview;
            }
            if (! $interview->is_open) {
                throw ValidationException::withMessages(['interview' => 'Form wawancara sudah ditutup. Hubungi petugas PMB untuk informasi selanjutnya.']);
            }
            if ($data['questions_version'] !== $interview->questions_version) {
                throw ValidationException::withMessages(['questions_version' => 'Pertanyaan telah diperbarui oleh petugas. Tinjau pertanyaan terbaru dan periksa kembali jawaban sebelum mengirim.']);
            }

            $answersByQuestion = [];
            foreach ($data['answers'] as $index => $answer) {
                $answersByQuestion[$answer['question_id']] = ['index' => $index, 'answer' => $answer['answer'] ?? ''];
            }
            $questions = $interview->questions;
            $questionIds = array_column($questions, 'id');
            if (count($answersByQuestion) !== count($questionIds) || array_diff(array_keys($answersByQuestion), $questionIds) !== []) {
                throw ValidationException::withMessages(['answers' => 'Daftar pertanyaan tidak sesuai. Muat ulang pertanyaan dan tinjau kembali jawaban Anda.']);
            }
            $snapshots = [];
            $errors = [];
            foreach ($questions as $question) {
                $answer = $answersByQuestion[$question['id']];
                if ($question['required'] && trim($answer['answer']) === '') {
                    $errors['answers.'.$answer['index'].'.answer'] = 'Pertanyaan ini wajib dijawab.';
                }
                $snapshots[] = [
                    'question_id' => $question['id'],
                    'question' => $question['prompt'],
                    'required' => (bool) $question['required'],
                    'answer' => $answer['answer'],
                ];
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $registrationKey = InterviewSubmission::registrationKey($data['registration_number']);
            if ($interview->submissions()->where('registration_key', $registrationKey)->exists()) {
                throw ValidationException::withMessages([
                    'registration_number' => 'Nomor registrasi ini sudah mengirim jawaban untuk form ini. Hubungi petugas PMB jika perlu memperbaiki data.',
                ]);
            }
            $studyProgram = StudyProgram::query()->whereKey($data['study_program_id'])->lockForUpdate()->first(['id', 'name']);
            if ($studyProgram === null) {
                throw ValidationException::withMessages(['study_program_id' => 'Program studi sudah tidak tersedia. Pilih kembali program studi yang diambil.']);
            }

            $interview->submissions()->create([
                'wave' => $data['wave'], 'period' => $data['period'], 'academic_year' => $data['academic_year'], 'campus' => $data['campus'],
                'name' => $data['name'],
                'registration_number' => $data['registration_number'],
                'registration_key' => $registrationKey,
                'study_program' => $studyProgram->getAttribute('name'),
                'answers' => $snapshots,
                'submission_token' => $data['submission_token'],
                'submitted_at' => now(),
            ]);

            return $interview;
        }, 5);

        $request->session()->put('interviews.receipts.'.$interview->id, true);

        return to_route('interviews.join', ['token' => $token]);
    }
}
