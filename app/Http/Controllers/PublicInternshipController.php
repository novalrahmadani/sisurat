<?php

namespace App\Http\Controllers;

use App\Http\Requests\InternshipAttendanceRequest;
use App\Http\Requests\InternshipRegistrationRequest;
use App\Models\InternshipParticipant;
use App\Support\InternshipDeviceAccess;
use App\Support\InternshipLocationAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicInternshipController extends Controller
{
    public function __construct(private InternshipDeviceAccess $devices, private InternshipLocationAccess $locations) {}

    public function show(Request $request): Response
    {
        return $this->renderPage($request);
    }

    public function session(Request $request): JsonResponse
    {
        $participant = $this->verifiedParticipant($request);

        return response()->json([
            'authenticated' => $participant !== null,
            'participant' => $participant?->only(['id', 'name', 'student_number', 'institution', 'study_program']),
        ])->header('Cache-Control', 'private, no-store');
    }

    /** @param array{token: string, available: bool}|null $recovery */
    private function renderPage(Request $request, ?array $recovery = null): Response
    {
        $now = CarbonImmutable::now('Asia/Jakarta');
        $now->locale('id');
        $participant = $this->verifiedParticipant($request);
        $record = $participant?->attendances()->whereDate('attendance_date', $now->toDateString())->first();

        return Inertia::render('internship/Public', [
            'participant' => $participant ? [...$participant->only(['id', 'name', 'student_number', 'institution', 'study_program']),
                'participant_category' => $participant->participant_category, 'category_label' => $participant->categoryLabel(), 'class_level' => $participant->class_level,
                'start_date' => $participant->start_date->toDateString(), 'end_date' => $participant->end_date->toDateString(),
                'can_attend' => $participant->canAttend($now->toDateString())] : null,
            'attendance' => $record?->presentation(),
            'today' => ['date' => $now->toDateString(), 'label' => $now->translatedFormat('l, d F Y'), 'server_time' => $now->toIso8601String()],
            'receipt' => $request->session()->pull('internship_receipt'),
            'location' => $this->locations->presentation(),
            'recovery' => $recovery,
        ])->withViewData('publicInternship', true);
    }

    public function register(InternshipRegistrationRequest $request): RedirectResponse
    {
        if ($this->verifiedParticipant($request)) {
            return to_route('internship.show');
        }
        try {
            DB::transaction(function () use ($request): void {
                $data = $request->validated();
                $pin = $data['pin'];
                unset($data['pin'], $data['pin_confirmation']);
                $participant = InternshipParticipant::query()->create([...$data, 'access_code_hash' => Hash::make($pin), 'is_active' => true, 'registration_source' => 'public']);
                $this->devices->remember($request, $participant);
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['student_number' => 'Identitas ini sudah terdaftar. Gunakan perangkat pendaftaran sebelumnya atau hubungi admin PMB.']);
        }
        $request->session()->put('internship_receipt', 'Pendaftaran peserta berhasil. Data Anda telah masuk ke administrasi magang PMB. Silakan lanjutkan absensi sesuai periode magang.');

        return to_route('internship.show');
    }

    public function recovery(Request $request, string $token): Response
    {
        $available = InternshipParticipant::query()->where('recovery_token_hash', hash('sha256', $token))->where('is_active', true)->where('recovery_expires_at', '>', now()->utc())->exists();

        return $this->renderPage($request, ['token' => $token, 'available' => $available]);
    }

    public function recover(Request $request, string $token): RedirectResponse
    {
        DB::transaction(function () use ($request, $token): void {
            $participant = InternshipParticipant::query()->where('recovery_token_hash', hash('sha256', $token))->where('is_active', true)->where('recovery_expires_at', '>', now()->utc())->lockForUpdate()->first();
            if (! $participant) {
                throw ValidationException::withMessages(['access' => 'Tautan tidak berlaku atau sudah digunakan. Minta tautan pemulihan baru kepada admin PMB.']);
            }
            $this->devices->remember($request, $participant);
            $participant->update(['recovery_token_hash' => null, 'recovery_expires_at' => null]);
        }, 5);

        return to_route('internship.show');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['student_number' => ['required', 'string', 'max:100'], 'access_code' => ['required', 'string', 'max:100']], [
            'student_number.required' => 'Isi NIM atau nomor identitas Anda.', 'access_code.required' => 'Isi kode absensi dari admin PMB.',
        ]);
        $participant = InternshipParticipant::query()->where('student_number', trim($data['student_number']))->first();
        if (! $participant || ! $participant->is_active || ! $participant->access_code_hash
            || ! Hash::check(strtoupper(trim($data['access_code'])), $participant->access_code_hash)) {
            throw ValidationException::withMessages(['access_code' => 'NIM atau kode absensi tidak sesuai, atau peserta tidak aktif. Hubungi admin PMB.']);
        }
        $request->session()->regenerate();
        $request->session()->put('internship_access', ['id' => $participant->id, 'fingerprint' => $participant->access_code_hash, 'expires_at' => now()->addHours(12)->timestamp]);

        return to_route('internship.show');
    }

    public function verifyPin(Request $request): RedirectResponse
    {
        return $this->verifyParticipantPin($request, 'pin');
    }

    private function verifyParticipantPin(Request $request, string $pinField): RedirectResponse
    {
        $data = $request->validate([
            'participant_category' => ['required', Rule::in(array_keys(InternshipParticipant::CATEGORIES))],
            'institution' => ['required', 'string', 'max:200'],
            'student_number' => ['required', 'string', 'max:100'],
            $pinField => ['required', 'digits:6'],
        ], [
            'participant_category.required' => 'Pilih kategori peserta.',
            'participant_category.in' => 'Kategori peserta tidak valid.',
            'institution.required' => 'Isi nama sekolah atau perguruan tinggi.',
            'student_number.required' => 'Isi NIM atau nomor identitas Anda.',
            $pinField.'.required' => 'Isi PIN absensi 6 digit.',
            $pinField.'.digits' => 'PIN absensi harus terdiri dari 6 angka.',
        ]);
        $participant = InternshipParticipant::query()
            ->where('participant_category', $data['participant_category'])
            ->where('institution', trim($data['institution']))
            ->where('student_number', trim($data['student_number']))
            ->first();
        if (! $participant || ! $participant->is_active || ! $participant->access_code_hash
            || ! Hash::check($data[$pinField], $participant->access_code_hash)) {
            throw ValidationException::withMessages([$pinField => 'NIM atau PIN tidak sesuai, atau peserta tidak aktif.']);
        }
        $request->session()->regenerate();
        $request->session()->put('internship_access', ['id' => $participant->id, 'fingerprint' => $participant->access_code_hash, 'expires_at' => now()->addHours(12)->timestamp]);

        return to_route('internship.show');
    }

    public function issuePin(Request $request): RedirectResponse
    {
        $participant = $this->verifiedParticipant($request, true);
        if (! $participant) {
            throw ValidationException::withMessages(['pin' => 'Masuk dengan PIN terlebih dahulu untuk mengubah PIN absensi.']);
        }
        $data = $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed'],
        ], [
            'pin.required' => 'Buat PIN absensi 6 digit.',
            'pin.digits' => 'PIN absensi harus terdiri dari 6 angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak sama.',
        ]);
        $participant->update(['access_code_hash' => Hash::make($data['pin'])]);
        $request->session()->put('internship_access', ['id' => $participant->id, 'fingerprint' => $participant->access_code_hash, 'expires_at' => now()->addHours(12)->timestamp]);
        $request->session()->put('internship_receipt', 'PIN absensi berhasil diperbarui.');

        return to_route('internship.show');
    }

    public function submit(InternshipAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $signature = $request->canonicalSignature();
        DB::transaction(function () use ($request, $data, $signature): void {
            $participant = $this->verifiedParticipant($request, true);
            if (! $participant) {
                throw ValidationException::withMessages(['access' => 'Akses perangkat tidak tersedia. Daftar sebagai peserta baru atau minta pemulihan akses kepada admin jika sudah terdaftar.']);
            }
            $now = CarbonImmutable::now('Asia/Jakarta');
            if (! $participant->canAttend($now->toDateString())) {
                throw ValidationException::withMessages(['action' => 'Hari ini berada di luar periode magang Anda. Hubungi admin untuk penyesuaian periode.']);
            }
            $this->locations->verify($request);
            $record = $participant->attendances()->whereDate('attendance_date', $now->toDateString())->first();
            $storedAt = $now->utc();
            $note = isset($data['note']) ? trim($data['note']) : null;
            if ($data['action'] === 'check_in') {
                if ($record) {
                    throw ValidationException::withMessages(['action' => 'Absensi masuk hari ini sudah tercatat. Silakan muat ulang untuk melihat status terbaru.']);
                }
                $participant->attendances()->create(['attendance_date' => $now->toDateString(), 'checked_in_at' => $storedAt,
                    'check_in_signature' => $signature, 'check_in_note' => $note]);
            } else {
                if (! $record) {
                    throw ValidationException::withMessages(['action' => 'Catat absensi masuk terlebih dahulu sebelum absen pulang.']);
                }
                if ($record->checked_out_at) {
                    throw ValidationException::withMessages(['action' => 'Absensi pulang hari ini sudah tercatat.']);
                }
                $record->update(['checked_out_at' => $storedAt, 'check_out_signature' => $signature, 'check_out_note' => $note]);
            }
        }, 5);
        $request->session()->put('internship_receipt', $data['action'] === 'check_in' ? 'Jam masuk berhasil dicatat. Saat selesai magang hari ini, kembali ke halaman ini untuk absen pulang.' : 'Jam pulang berhasil dicatat. Absensi hari ini sudah lengkap.');

        return to_route('internship.show');
    }

    public function leave(Request $request): RedirectResponse
    {
        $this->devices->forget($request);

        return to_route('internship.show');
    }

    private function verifiedParticipant(Request $request, bool $lock = false): ?InternshipParticipant
    {
        $deviceParticipant = $this->devices->participant($request, $lock);
        if ($deviceParticipant) {
            return $deviceParticipant;
        }
        $access = $request->session()->get('internship_access');
        if (! is_array($access) || ! isset($access['id'], $access['fingerprint'], $access['expires_at']) || $access['expires_at'] <= now()->timestamp) {
            $request->session()->forget('internship_access');

            return null;
        }
        $query = InternshipParticipant::query()->whereKey($access['id']);
        if ($lock) {
            $query->lockForUpdate();
        }
        $participant = $query->first();
        if (! $participant || ! $participant->is_active || ! is_string($participant->access_code_hash) || ! hash_equals($participant->access_code_hash, $access['fingerprint'])) {
            $request->session()->forget('internship_access');

            return null;
        }

        return $participant;
    }
}
