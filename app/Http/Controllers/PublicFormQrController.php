<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use App\Models\InterviewForm;
use App\Models\QuranExam;
use App\Support\PublicFormLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PublicFormQrController extends Controller
{
    public function __construct(private PublicFormLinks $links) {}

    public function attendance(Request $request, AttendanceEvent $attendance): Response
    {
        Gate::authorize('view', $attendance);

        return $this->links->qrCode('attendance.join', ['token' => $attendance->public_token], 'qr-daftar-hadir-'.$attendance->id.'.png', $request->boolean('download'));
    }

    public function interview(Request $request, InterviewForm $interview): Response
    {
        Gate::authorize('view', $interview);

        return $this->links->qrCode('interviews.join', ['token' => $interview->public_token], 'qr-wawancara-'.$interview->id.'.png', $request->boolean('download'));
    }

    public function quran(Request $request, QuranExam $quranExam): Response
    {
        Gate::authorize('view', $quranExam);

        return $this->links->qrCode('quran-exams.join', ['token' => $quranExam->public_token], 'qr-penguji-mq-'.$quranExam->id.'.png', $request->boolean('download'));
    }

    public function internship(Request $request): Response
    {
        Gate::authorize('manage-internships');

        return $this->links->qrCode('internship.show', [], 'qr-absensi-magang.png', $request->boolean('download'));
    }

    public function pmbProspects(Request $request): Response
    {
        abort_unless($request->user()?->isAdministrator(), 403);

        return $this->links->qrCode('pmb-prospects.join', [], 'qr-calon-mahasiswa.png', $request->boolean('download'));
    }
}

