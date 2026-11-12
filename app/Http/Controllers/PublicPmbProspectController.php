<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePmbProspectSubmissionRequest;
use App\Models\PmbProspectSetting;
use App\Models\PmbProspectSubmission;
use App\Models\StudyProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicPmbProspectController extends Controller
{
    public function join(Request $request): Response
    {
        $settings = PmbProspectSetting::query()->findOrFail(1);
        $response = Inertia::render('pmb-prospects/Join', [
            'isOpen' => $settings->is_open,
            'studyPrograms' => $settings->is_open
                ? StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'submitted' => (bool) $request->session()->get('pmb_prospects.submitted', false),
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }

    public function submit(StorePmbProspectSubmissionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data): void {
            $settings = PmbProspectSetting::query()->lockForUpdate()->findOrFail(1);
            if (PmbProspectSubmission::query()->where('submission_token', $data['submission_token'])->exists()) {
                return;
            }
            if (! $settings->is_open) {
                throw ValidationException::withMessages([
                    'form' => 'Formulir pendaftaran sedang ditutup. Hubungi panitia PMB untuk informasi selanjutnya.',
                ]);
            }
            $studyProgram = StudyProgram::query()->findOrFail($data['study_program_id']);
            PmbProspectSubmission::query()->create([
                'name' => $data['name'],
                'study_program' => $studyProgram->name,
                'school_name' => $data['school_name'],
                'school_district' => $data['school_district'],
                'school_address' => $data['school_address'],
                'affiliate_name' => $data['affiliate_name'],
                'submission_token' => $data['submission_token'],
                'submitted_at' => now(),
            ]);
        }, 5);

        $request->session()->flash('pmb_prospects.submitted', true);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data calon mahasiswa berhasil dikirim.']);

        return to_route('pmb-prospects.join');
    }
}
