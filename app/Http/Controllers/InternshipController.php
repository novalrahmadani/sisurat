<?php

namespace App\Http\Controllers;

use App\Http\Requests\InternshipParticipantRequest;
use App\Models\InternshipAttendance;
use App\Models\InternshipParticipant;
use App\Support\InternshipDeviceAccess;
use App\Support\InternshipLocationAccess;
use App\Support\PublicFormLinks;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InternshipController extends Controller
{
    public function __construct(private InternshipDeviceAccess $devices, private InternshipLocationAccess $locations) {}

    public function index(Request $request, PublicFormLinks $links): Response
    {
        $filters = $this->filters($request);
        $participants = InternshipParticipant::query()->withCount('attendances')->when($filters['q'], fn (Builder $query, string $q) => $this->searchParticipants($query, $q))
            ->orderBy('name')->paginate(12, ['*'], 'participants_page')->withQueryString();
        $records = $this->records($filters)->with('participant')->paginate(25, ['*'], 'records_page')->withQueryString();
        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();

        return Inertia::render('internship/Index', [
            'participants' => $participants->through(fn (InternshipParticipant $participant): array => [
                ...$this->participantData($participant),
                'attendance_count' => (int) $participant->getAttribute('attendances_count'),
            ]),
            'records' => $records->through(fn (InternshipAttendance $record): array => [...$record->presentation(), 'participant' => $this->participantData($record->participant)]),
            'filters' => $filters, 'today' => $today,
            'stats' => ['active' => InternshipParticipant::query()->where('is_active', true)->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count(),
                'present' => InternshipAttendance::query()->whereDate('attendance_date', $today)->count(),
                'pending' => InternshipAttendance::query()->whereDate('attendance_date', $today)->whereNull('checked_out_at')->count()],
            'issuedAccess' => $request->session()->pull('internship_issued_access'),
            'location' => $this->locations->presentation(),
            'publicAttendanceUrl' => $links->url('internship.show'),
        ]);
    }

    public function store(InternshipParticipantRequest $request): RedirectResponse
    {
        $participant = InternshipParticipant::query()->create([...$request->validated(), 'registration_source' => 'admin']);
        $request->session()->put('internship_issued_access', ['name' => $participant->name, 'url' => $this->devices->recoveryUrl($participant)]);

        return to_route('internships.index', ['tab' => 'participants']);
    }

    public function update(InternshipParticipantRequest $request, InternshipParticipant $participant): RedirectResponse
    {
        $participant->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data peserta magang diperbarui.']);

        return to_route('internships.index', ['tab' => 'participants']);
    }

    public function recoverAccess(Request $request, InternshipParticipant $participant): RedirectResponse
    {
        $request->session()->put('internship_issued_access', ['name' => $participant->name, 'url' => $this->devices->recoveryUrl($participant)]);

        return to_route('internships.index', ['tab' => 'participants']);
    }

    public function destroy(Request $request, InternshipParticipant $participant): RedirectResponse
    {
        $filters = $this->filters($request);

        DB::transaction(function () use ($participant): void {
            $lockedParticipant = InternshipParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            $lockedParticipant->attendances()->delete();
            $lockedParticipant->delete();
        }, 5);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Peserta magang beserta riwayat absensi dan tanda tangannya berhasil dihapus.']);

        return to_route('internships.index', [...$filters, 'tab' => 'participants']);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        return response()->streamDownload(function () use ($filters): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('File rekap belum dapat dibuat. Silakan coba kembali.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tanggal', 'NIS/NISN / NIM', 'Nama', 'Asal Sekolah / Perguruan Tinggi', 'Jurusan / Program Studi', 'Jam Masuk (WIB)', 'Jam Pulang (WIB)', 'Durasi (menit)', 'Keterangan Masuk', 'Keterangan Pulang', 'Status', 'Kategori Peserta', 'Kelas / Semester'], ',', '"', '');
            foreach ($this->records($filters)->with('participant')->lazy(100) as $record) {
                $data = $record->presentation();
                fputcsv($output, [$data['date'], "'".$record->participant->student_number, $this->csvCell($record->participant->name),
                    $this->csvCell($record->participant->institution), $this->csvCell($record->participant->study_program ?? ''),
                    $data['checked_in_at'], $data['checked_out_at'] ?? '', $data['duration_minutes'] ?? '',
                    $this->csvCell($data['check_in_note'] ?? ''), $this->csvCell($data['check_out_note'] ?? ''), $data['checked_out_at'] ? 'Lengkap' : 'Belum absen pulang', $record->participant->categoryLabel(), $this->csvCell($record->participant->class_level ?? '')], ',', '"', '');
            }
            fclose($output);
        }, 'absensi-magang-'.$filters['month'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function print(Request $request): HttpResponse
    {
        $filters = $this->filters($request);
        $month = CarbonImmutable::parse($filters['month'].'-01');
        $month->locale('id');

        return response()->view('internship.print', ['records' => $this->records($filters)->with('participant')->lazy(100),
            'monthLabel' => $month->translatedFormat('F Y'),
            'filters' => $filters])->header('Cache-Control', 'private, no-store');
    }

    public function signature(InternshipAttendance $attendance, string $kind): HttpResponse
    {
        $signature = $kind === 'in' ? $attendance->check_in_signature : $attendance->check_out_signature;
        abort_unless(is_string($signature) && $signature !== '', 404);

        $png = base64_decode($signature, true);
        if ($png === false) {
            abort(404);
        }

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, mixed> */
    private function participantData(InternshipParticipant $participant): array
    {
        return [...$participant->only(['id', 'name', 'student_number', 'institution', 'study_program', 'is_active']),
            'participant_category' => $participant->participant_category, 'category_label' => $participant->categoryLabel(), 'class_level' => $participant->class_level, 'registration_source' => $participant->registration_source,
            'start_date' => $participant->start_date->toDateString(), 'end_date' => $participant->end_date->toDateString()];
    }

    /** @return array{q: string, month: string, tab: string} */
    private function filters(Request $request): array
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'month' => ['nullable', 'date_format:Y-m'], 'tab' => ['nullable', 'in:records,participants']]);

        return ['q' => trim($data['q'] ?? ''), 'month' => $data['month'] ?? CarbonImmutable::now('Asia/Jakarta')->format('Y-m'), 'tab' => $data['tab'] ?? 'records'];
    }

    /**
     * @param  array{q: string, month: string, tab: string}  $filters
     * @return Builder<InternshipAttendance>
     */
    private function records(array $filters): Builder
    {
        $start = CarbonImmutable::parse($filters['month'].'-01');

        return InternshipAttendance::query()->where('attendance_date', '>=', $start->toDateString())
            ->where('attendance_date', '<', $start->addMonth()->toDateString())
            ->when($filters['q'], fn (Builder $query, string $q) => $query->whereHas('participant', fn (Builder $participant) => $this->searchParticipants($participant, $q)))
            ->orderByDesc('attendance_date')->orderBy('id');
    }

    /**
     * @param  Builder<InternshipParticipant>  $query
     * @return Builder<InternshipParticipant>
     */
    private function searchParticipants(Builder $query, string $text): Builder
    {
        return $query->where(fn (Builder $nested) => $nested->where('name', 'like', '%'.$text.'%')->orWhere('student_number', 'like', '%'.$text.'%')->orWhere('institution', 'like', '%'.$text.'%'));
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[\s]*[=+@\-]/u', $value) === 1 || str_starts_with($value, "\t") || str_starts_with($value, "\r") ? "'".$value : $value;
    }
}
