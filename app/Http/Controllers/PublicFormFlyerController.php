<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use App\Models\InterviewForm;
use App\Models\QuranExam;
use App\Support\PublicFormFlyer;
use App\Support\PublicFormLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PublicFormFlyerController extends Controller
{
    public function __construct(private PublicFormLinks $links, private PublicFormFlyer $flyers) {}

    public function attendance(Request $request, AttendanceEvent $attendance): Response
    {
        Gate::authorize('view', $attendance);

        return $this->flyer($request, 'attendance.join', ['token' => $attendance->public_token], $attendance->title,
            'DAFTAR HADIR ONLINE', 'Isi identitas dan tanda tangan melalui formulir.', 'flyer-daftar-hadir-'.$attendance->id.'.png');
    }

    public function interview(Request $request, InterviewForm $interview): Response
    {
        Gate::authorize('view', $interview);

        return $this->flyer($request, 'interviews.join', ['token' => $interview->public_token], $interview->title,
            'TES WAWANCARA PMB', 'Lengkapi identitas dan ketik jawaban Anda.', 'flyer-wawancara-'.$interview->id.'.png');
    }

    public function quran(Request $request, QuranExam $quranExam): Response
    {
        Gate::authorize('view', $quranExam);

        return $this->flyer($request, 'quran-exams.join', ['token' => $quranExam->public_token], $quranExam->title,
            'TES BACA AL-QURAN', 'Khusus penguji yang ditunjuk oleh panitia PMB.', 'flyer-penguji-mq-'.$quranExam->id.'.png');
    }

    public function internship(Request $request): Response
    {
        Gate::authorize('manage-internships');

        return $this->flyer($request, 'internship.show', [], 'Absensi Magang PMB UNUHA',
            'ABSENSI MAGANG ONLINE', 'Untuk peserta magang SMK, SMA, dan mahasiswa.', 'flyer-absensi-magang.png');
    }

    /** @param array<string, int|string> $parameters */
    private function flyer(Request $request, string $route, array $parameters, string $title, string $category, string $instruction, string $filename): Response
    {
        $image = $this->flyers->image($this->links->qrImage($route, $parameters), $title, $category, $this->links->url($route, $parameters), $instruction);

        return response($image, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
