<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicQuranAssessmentRequest;
use App\Models\QuranAssessment;
use App\Models\QuranExam;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicQuranAssessmentController extends Controller
{
    public function join(Request $request, string $token): Response
    {
        $exam = QuranExam::query()->with('creator')->where('public_token', $token)->firstOrFail();
        $open = $exam->is_open && ($exam->creator->is_active ?? false);

        $response = Inertia::render('quran-exams/Join', [
            'exam' => [...$exam->only(['wave', 'period', 'campus']), 'title' => $exam->title, 'description' => $exam->description,
                'assessment_date' => $exam->assessment_date->format('Y-m-d'), 'is_open' => $open, 'version' => $exam->version],
            'token' => $token, 'studyPrograms' => $open ? StudyProgram::query()->orderBy('name')->get(['id', 'name']) : [],
            'receipt' => $request->session()->get('quran.receipts.'.$exam->id),
        ])->toResponse($request);
        $response->headers->add(['Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer']);

        return $response;
    }

    public function submit(PublicQuranAssessmentRequest $request, string $token): RedirectResponse
    {
        $data = $request->validated();
        $publicExam = QuranExam::query()->where('public_token', $token)->firstOrFail();
        $saved = DB::transaction(function () use ($publicExam, $token, $data): QuranAssessment {
            $owner = User::query()->lockForUpdate()->find($publicExam->created_by);
            $exam = QuranExam::query()->lockForUpdate()->findOrFail($publicExam->id);
            abort_unless(hash_equals($exam->public_token, $token), 404);
            if ($owner === null || ! $owner->is_active) {
                throw ValidationException::withMessages(['exam' => 'Penilaian sedang ditutup. Hubungi admin PMB.']);
            }
            $existing = QuranAssessment::query()->where('created_by', $owner->id)->where('submission_token', $data['submission_token'])->first();
            if ($existing !== null) {
                if ($existing->quran_exam_id !== $exam->id) {
                    throw ValidationException::withMessages(['submission_token' => 'Identitas pengiriman berasal dari kegiatan lain. Muat ulang formulir.']);
                }

                return $existing;
            }
            if (! $exam->is_open) {
                throw ValidationException::withMessages(['exam' => 'Penilaian tes ini sudah ditutup oleh admin PMB.']);
            }
            if ($data['exam_version'] !== $exam->version) {
                throw ValidationException::withMessages(['exam_version' => 'Informasi tes diperbarui. Muat ulang halaman dan periksa isian sebelum mengirim.']);
            }
            $registrationKey = QuranAssessment::registrationKey($data['registration_number']);
            if ($exam->assessments()->where('registration_key', $registrationKey)->exists()) {
                throw ValidationException::withMessages(['registration_number' => 'Nomor registrasi ini sudah dinilai pada kegiatan tes ini. Hubungi admin untuk memperbaiki nilai.']);
            }
            $program = StudyProgram::query()->lockForUpdate()->whereKey($data['study_program_id'])->first();
            $programName = $program?->getAttribute('name');
            if (! is_string($programName)) {
                throw ValidationException::withMessages(['study_program_id' => 'Program studi tidak tersedia. Pilih kembali program studi.']);
            }

            return $exam->assessments()->create([
                'wave' => $data['wave'], 'period' => $data['period'], 'academic_year' => $data['academic_year'], 'campus' => $data['campus'],
                'name' => $data['name'], 'registration_number' => $data['registration_number'], 'registration_key' => $registrationKey,
                'study_program' => $programName, 'assessment_date' => $exam->assessment_date,
                'examiner_name' => $data['examiner_name'], 'reading_reference' => $data['reading_reference'],
                'fasohah_score' => $data['fasohah_score'], 'tajwid_score' => $data['tajwid_score'],
                'makhraj_score' => $data['makhraj_score'], 'fluency_score' => $data['fluency_score'],
                'result' => QuranAssessment::resultForScores($data['fasohah_score'], $data['tajwid_score'], $data['makhraj_score'], $data['fluency_score']),
                'notes' => $data['notes'] ?? null, 'created_by' => $owner->id,
                'submission_token' => $data['submission_token'], 'version' => (string) Str::uuid(),
            ]);
        }, 5);
        $request->session()->put('quran.receipts.'.$publicExam->id, [
            'name' => $saved->name, 'registration_number' => $saved->registration_number,
            'average_score' => $saved->averageScore(), 'result' => $saved->result,
        ]);

        return to_route('quran-exams.join', ['token' => $token]);
    }
}
