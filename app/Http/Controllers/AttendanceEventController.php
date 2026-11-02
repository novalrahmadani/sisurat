<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceEventRequest;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\PmbSignatory;
use App\Support\PublicFormLinks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceEventController extends Controller
{
    public function __construct(private PublicFormLinks $links) {}

    public function index(Request $request, ?string $attendanceType = null): Response|RedirectResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['all', 'open', 'closed'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($attendanceType === null) {
            return to_route('attendance.meetings.index', $filters);
        }
        abort_unless(in_array($attendanceType, AttendanceEvent::TYPES, true), 404);

        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? 'all';
        $creatorId = $request->user()->id;
        $events = $this->eventsQuery((int) $creatorId, $attendanceType, $search, $status)
            ->select(['id', 'type', 'title', 'event_date', 'location', 'is_open'])
            ->withCount('records as entries_count')
            ->orderByDesc('event_date')->orderByDesc('id')->paginate(15)->withQueryString()
            ->through(fn (AttendanceEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'title' => $event->title,
                'event_date' => $event->event_date->toDateString(),
                'location' => $event->location,
                'is_open' => $event->is_open,
                'entries_count' => $event->entries_count,
            ]);

        return Inertia::render('attendance/Index', [
            'attendanceType' => $attendanceType,
            'events' => $events,
            'filters' => ['q' => $search, 'status' => $status],
            'stats' => [
                'events' => AttendanceEvent::query()->where('created_by', $creatorId)->where('type', $attendanceType)->count(),
                'open' => AttendanceEvent::query()->where('created_by', $creatorId)->where('type', $attendanceType)->where('is_open', true)->count(),
                'participants' => AttendanceRecord::query()
                    ->whereHas('event', fn (Builder $query) => $query->where('created_by', $creatorId)->where('type', $attendanceType))->count(),
            ],
        ]);
    }

    public function create(string $attendanceType = AttendanceEvent::TYPE_MEETING): Response
    {
        abort_unless(in_array($attendanceType, AttendanceEvent::TYPES, true), 404);

        return Inertia::render('attendance/EventForm', ['event' => null, 'attendanceType' => $attendanceType]);
    }

    public function store(AttendanceEventRequest $request): RedirectResponse
    {
        $event = AttendanceEvent::query()->create([
            ...$request->validated(),
            'public_token' => Str::random(64),
            'created_by' => $request->user()->id,
            'pmb_signatory_id' => PmbSignatory::current()->id,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daftar hadir berhasil dibuat. Bagikan tautan kepada peserta.']);

        return to_route('attendance.show', $event);
    }

    public function show(Request $request, AttendanceEvent $attendance): Response
    {
        Gate::authorize('view', $attendance);

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $search = trim($filters['q'] ?? '');
        $records = $this->recordsQuery($attendance, $search)
            ->select(['id', 'attendance_event_id', 'name', 'position', 'institution', 'registration_number', 'study_program', 'school_origin', 'phone_number', 'signed_at'])
            ->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn (AttendanceRecord $record): array => [
                ...$record->only(['id', 'name', 'position', 'institution', 'registration_number', 'study_program', 'school_origin', 'phone_number']),
                'signed_at' => $record->signed_at->toIso8601String(),
                'signature_url' => route('attendance.signature', ['attendance' => $attendance, 'record' => $record]),
            ]);

        return Inertia::render('attendance/Show', [
            'event' => [...$this->eventData($attendance), 'entries_count' => $attendance->records()->count()],
            'records' => $records,
            'filters' => ['q' => $search],
            'public_url' => $this->links->url('attendance.join', ['token' => $attendance->public_token]),
        ]);
    }

    public function edit(AttendanceEvent $attendance): Response
    {
        Gate::authorize('update', $attendance);

        return Inertia::render('attendance/EventForm', [
            'event' => [...$this->eventData($attendance), 'entries_count' => $attendance->records()->count()],
            'attendanceType' => $attendance->type,
        ]);
    }

    public function update(AttendanceEventRequest $request, AttendanceEvent $attendance): RedirectResponse
    {
        Gate::authorize('update', $attendance);

        DB::transaction(function () use ($request, $attendance): void {
            $event = AttendanceEvent::query()->lockForUpdate()->findOrFail($attendance->id);
            $data = $request->validated();
            $changesParticipantFields = ($event->type === AttendanceEvent::TYPE_PMB_TEST) !== ($data['type'] === AttendanceEvent::TYPE_PMB_TEST);
            if ($changesParticipantFields && $event->records()->exists()) {
                throw ValidationException::withMessages([
                    'type' => 'Kategori Tes PMB tidak dapat diubah ke Rapat/Kegiatan Lainnya atau sebaliknya setelah ada peserta. Buat kegiatan baru agar data peserta tetap sesuai.',
                ]);
            }
            $event->update($data);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kegiatan dan status daftar hadir berhasil diperbarui.']);

        return to_route('attendance.show', $attendance);
    }

    public function export(AttendanceEvent $attendance): StreamedResponse
    {
        Gate::authorize('view', $attendance);

        $filename = 'daftar-hadir-'.(Str::slug($attendance->title) ?: $attendance->id).'.csv';

        return response()->streamDownload(function () use ($attendance): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            $isPmbTest = $attendance->type === AttendanceEvent::TYPE_PMB_TEST;
            $headers = $isPmbTest
                ? ['No', 'Nama', 'Nomor Registrasi', 'Program Studi/Jurusan yang Diambil', 'Asal Sekolah', 'No. HP/WhatsApp', 'Waktu Hadir (WIB)']
                : ['No', 'Nama', 'Jabatan', 'Instansi/Institusi', 'Waktu Hadir (WIB)'];
            fputcsv($output, $headers, ',', '"', '');
            $number = 0;
            foreach ($attendance->records()->select(['id', 'attendance_event_id', 'name', 'position', 'institution', 'registration_number', 'study_program', 'school_origin', 'phone_number', 'signed_at'])->lazyById(250) as $record) {
                $participantFields = $isPmbTest
                    ? [$this->csvIdentifier($record->registration_number ?? ''), $this->csvCell($record->study_program ?? ''), $this->csvCell($record->school_origin ?? ''), $this->csvIdentifier($record->phone_number ?? '')]
                    : [$this->csvCell($record->position ?? ''), $this->csvCell($record->institution ?? '')];
                fputcsv($output, [
                    ++$number,
                    $this->csvCell($record->name),
                    ...$participantFields,
                    $record->signed_at->timezone('Asia/Jakarta')->format('d-m-Y H:i:s'),
                ], ',', '"', '');
            }
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function print(AttendanceEvent $attendance): HttpResponse
    {
        Gate::authorize('view', $attendance);
        $approval = ($attendance->pmbSignatory ?? PmbSignatory::legacy())->presentation();
        if ($attendance->pmb_signatory_id === null) {
            $approval['title'] = 'Kepala Pusat PMB';
        }

        return response()->view('attendance.print', [
            'event' => $attendance,
            'approval' => $approval,
            'records' => $attendance->records()->orderBy('id')->get(['id', 'attendance_event_id', 'name', 'position', 'institution', 'registration_number', 'study_program', 'school_origin', 'phone_number', 'signed_at']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function signature(AttendanceEvent $attendance, AttendanceRecord $record): HttpResponse
    {
        Gate::authorize('view', $attendance);

        abort_unless($record->attendance_event_id === $attendance->id, 404);

        return response(base64_decode($record->signature_png, true), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="tanda-tangan-'.$record->id.'.png"',
        ]);
    }

    public function destroy(Request $request, AttendanceEvent $attendance): RedirectResponse
    {
        Gate::authorize('delete', $attendance);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['all', 'open', 'closed'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $type = DB::transaction(function () use ($attendance): string {
            $event = AttendanceEvent::query()->lockForUpdate()->findOrFail($attendance->id);
            Gate::authorize('delete', $event);
            $type = $event->type;
            $event->delete();

            return $type;
        }, 5);
        $remaining = $this->eventsQuery((int) $request->user()->id, $type, trim($filters['q'] ?? ''), $filters['status'] ?? 'all')->count();
        $lastPage = max(1, (int) ceil($remaining / 15));
        $filters['page'] = min((int) ($filters['page'] ?? 1), $lastPage);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kegiatan daftar hadir beserta seluruh data peserta dan tanda tangannya berhasil dihapus.']);

        return to_route($this->categoryIndexRoute($type), $filters);
    }

    public function destroyRecord(Request $request, AttendanceEvent $attendance, AttendanceRecord $record): RedirectResponse
    {
        Gate::authorize('delete', $attendance);
        abort_unless((int) $record->attendance_event_id === (int) $attendance->id, 404);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($attendance, $record): void {
            $event = AttendanceEvent::query()->lockForUpdate()->findOrFail($attendance->id);
            Gate::authorize('delete', $event);
            $event->records()->whereKey($record->id)->lockForUpdate()->firstOrFail()->delete();
        }, 5);
        $remaining = $this->recordsQuery($attendance, trim($filters['q'] ?? ''))->count();
        $lastPage = max(1, (int) ceil($remaining / 20));
        $filters['page'] = min((int) ($filters['page'] ?? 1), $lastPage);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data peserta beserta tanda tangannya berhasil dihapus.']);

        return to_route('attendance.show', ['attendance' => $attendance->id, ...$filters]);
    }

    private function eventsQuery(int $creatorId, string $type, string $search, string $status): Builder
    {
        return AttendanceEvent::query()->where('created_by', $creatorId)->where('type', $type)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')->orWhere('location', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== 'all', fn (Builder $query) => $query->where('is_open', $status === 'open'));
    }

    private function recordsQuery(AttendanceEvent $attendance, string $search): Builder
    {
        $isPmbTest = $attendance->type === AttendanceEvent::TYPE_PMB_TEST;

        return AttendanceRecord::query()->whereBelongsTo($attendance, 'event')
            ->when($search !== '', function (Builder $query) use ($search, $isPmbTest): void {
                $query->where(function (Builder $query) use ($search, $isPmbTest): void {
                    $query->where('name', 'like', '%'.$search.'%');
                    if ($isPmbTest) {
                        $query->orWhere('registration_number', 'like', '%'.$search.'%')
                            ->orWhere('study_program', 'like', '%'.$search.'%')
                            ->orWhere('school_origin', 'like', '%'.$search.'%')
                            ->orWhere('phone_number', 'like', '%'.$search.'%');
                    } else {
                        $query->orWhere('position', 'like', '%'.$search.'%')
                            ->orWhere('institution', 'like', '%'.$search.'%');
                    }
                });
            });
    }

    private function categoryIndexRoute(string $type): string
    {
        return match ($type) {
            AttendanceEvent::TYPE_PMB_TEST => 'attendance.pmb-tests.index',
            AttendanceEvent::TYPE_OTHER => 'attendance.other.index',
            default => 'attendance.meetings.index',
        };
    }

    /**
     * @return array{id: int, type: string, title: string, event_date: string, location: ?string, description: ?string, is_open: bool}
     */
    private function eventData(AttendanceEvent $event): array
    {
        return [
            ...$event->only(['id', 'type', 'title', 'location', 'description', 'is_open']),
            'event_date' => $event->event_date->toDateString(),
        ];
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F\x{FEFF}\x{200B}]*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }

    private function csvIdentifier(string $value): string
    {
        return ctype_digit($value) ? "'".$value : $this->csvCell($value);
    }
}
