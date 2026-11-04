<?php

namespace App\Support;

use App\Models\InternshipParticipant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InternshipPinAccess
{
    public const UNAVAILABLE_MESSAGE = 'PIN tersebut tidak dapat digunakan. Silakan pilih PIN lain.';

    public function assign(InternshipParticipant $participant, string $pin): void
    {
        try {
            $this->persist($participant, $pin);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['pin' => self::UNAVAILABLE_MESSAGE]);
        }
    }

    public function issue(InternshipParticipant $participant): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $pin = (string) random_int(10_000_000, 99_999_999);
            $lookup = $this->fingerprint($pin);
            if (InternshipParticipant::query()->where('pin_lookup_hash', $lookup)->exists()) {
                continue;
            }

            try {
                // A savepoint lets a concurrent PIN collision be retried without
                // rolling back the participant registration that owns this call.
                $this->persist($participant, $pin);

                return $pin;
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new RuntimeException('PIN belum dapat dibuat. Silakan coba kembali.');
    }

    private function persist(InternshipParticipant $participant, string $pin): void
    {
        DB::transaction(fn () => $participant->update([
            'pin_lookup_hash' => $this->fingerprint($pin),
            'access_code_hash' => Hash::make($pin),
            'recovery_token_hash' => null,
            'recovery_expires_at' => null,
        ]));
    }

    public function fingerprint(string $pin): string
    {
        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Kunci aplikasi belum dikonfigurasi.');
        }

        return hash_hmac('sha256', 'internship-pin:'.$pin, $key);
    }
}
