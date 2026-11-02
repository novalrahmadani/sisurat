<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceSubmissionRequest;
use App\Models\AttendanceEvent;
use App\Models\StudyProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicAttendanceController extends Controller
{
    public function join(Request $request, string $token): Response
    {
        $event = AttendanceEvent::query()->where('public_token', $token)->firstOrFail();

        return Inertia::render('attendance/Join', [
            'event' => [
                ...$event->only(['type', 'title', 'location', 'description', 'is_open']),
                'event_date' => $event->event_date->toDateString(),
            ],
            'token' => $token,
            'studyPrograms' => $event->type === AttendanceEvent::TYPE_PMB_TEST
                ? StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'submitted' => (bool) $request->session()->get('attendance.receipts.'.$event->id, false),
        ]);
    }

    public function submit(AttendanceSubmissionRequest $request, string $token): RedirectResponse
    {
        $data = $request->validated();
        $signature = $request->canonicalSignature();
        $validatedType = $request->validatedAttendanceType();
        $event = DB::transaction(function () use ($data, $signature, $validatedType, $token): AttendanceEvent {
            $event = AttendanceEvent::query()->where('public_token', $token)->lockForUpdate()->firstOrFail();
            if ($event->records()->where('submission_token', $data['submission_token'])->exists()) {
                return $event;
            }
            if ($event->type !== $validatedType) {
                throw ValidationException::withMessages(['event' => 'Kategori kegiatan baru saja berubah. Periksa isian pada halaman terbaru, lalu kirim kembali.']);
            }
            if (! $event->is_open) {
                throw ValidationException::withMessages(['event' => 'Daftar hadir untuk kegiatan ini sudah ditutup. Hubungi panitia bila memerlukan bantuan.']);
            }
            $isPmbTest = $event->type === AttendanceEvent::TYPE_PMB_TEST;
            $studyProgram = null;
            if ($isPmbTest) {
                $studyProgram = StudyProgram::query()->whereKey($data['study_program_id'])->lockForUpdate()->first(['id', 'name']);
                if ($studyProgram === null) {
                    throw ValidationException::withMessages(['study_program_id' => 'Program studi yang dipilih sudah tidak tersedia. Muat ulang halaman dan pilih kembali.']);
                }
            }
            $event->records()->create([
                'name' => $data['name'],
                'position' => $isPmbTest ? null : $data['position'],
                'institution' => $isPmbTest ? null : $data['institution'],
                'registration_number' => $isPmbTest ? $data['registration_number'] : null,
                'study_program' => $studyProgram?->name,
                'school_origin' => $isPmbTest ? $data['school_origin'] : null,
                'phone_number' => $isPmbTest ? $data['phone_number'] : null,
                'submission_token' => $data['submission_token'],
                'signature_png' => $signature,
                'signed_at' => now(),
            ]);

            return $event;
        }, 5);

        $request->session()->put('attendance.receipts.'.$event->id, true);

        return to_route('attendance.join', ['token' => $token]);
    }
}
