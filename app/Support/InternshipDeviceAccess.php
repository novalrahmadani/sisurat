<?php

namespace App\Support;

use App\Models\InternshipParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class InternshipDeviceAccess
{
    public const COOKIE = 'sisurat_internship_device';

    public const SESSION = 'internship_device_access';

    public function __construct(private PublicFormLinks $links) {}

    public function participant(Request $request, bool $lock = false): ?InternshipParticipant
    {
        $proofs = [];
        $cookie = $request->cookie(self::COOKIE);
        if (is_string($cookie) && preg_match('/^([1-9][0-9]*):([A-Za-z0-9]{64})$/', $cookie, $matches) === 1) {
            $proofs[] = ['id' => $matches[1], 'fingerprint' => hash('sha256', $matches[2])];
        }

        $sessionProof = $request->session()->get(self::SESSION);
        if (is_array($sessionProof) && isset($sessionProof['id'], $sessionProof['fingerprint'])
            && is_int($sessionProof['id']) && is_string($sessionProof['fingerprint'])) {
            $proofs[] = $sessionProof;
        }

        foreach ($proofs as $proof) {
            $query = InternshipParticipant::query()->whereKey($proof['id']);
            if ($lock) {
                $query->lockForUpdate();
            }
            $participant = $query->first();
            if ($participant && $participant->is_active && $participant->device_token_hash
                && $participant->device_expires_at && $participant->device_expires_at->isFuture()
                && hash_equals($participant->device_token_hash, $proof['fingerprint'])) {
                return $participant;
            }
        }

        $request->session()->forget(self::SESSION);

        return null;
    }

    public function remember(Request $request, InternshipParticipant $participant): void
    {
        $token = Str::random(64);
        $fingerprint = hash('sha256', $token);
        $participant->update(['device_token_hash' => $fingerprint, 'device_expires_at' => now()->addDays(180)->utc()]);
        $request->session()->regenerate();
        $request->session()->forget(['internship_access', 'internship_issued_pin']);
        $request->session()->put(self::SESSION, ['id' => (int) $participant->id, 'fingerprint' => $fingerprint]);
        Cookie::queue(Cookie::make(self::COOKIE, $participant->id.':'.$token, 60 * 24 * 180, '/absensi-magang', null, $request->isSecure(), true, false, 'lax'));
    }

    public function forget(Request $request): void
    {
        $this->participant($request)?->update(['device_token_hash' => null, 'device_expires_at' => null]);
        Cookie::queue(Cookie::forget(self::COOKIE, '/absensi-magang'));
        $request->session()->forget([self::SESSION, 'internship_access', 'internship_receipt', 'internship_issued_pin']);
    }

    public function recoveryUrl(InternshipParticipant $participant): string
    {
        $token = Str::random(64);
        $participant->update(['recovery_token_hash' => hash('sha256', $token), 'recovery_expires_at' => now()->addMinutes(30)->utc(), 'device_token_hash' => null,
            'access_code_hash' => $participant->pin_lookup_hash ? $participant->access_code_hash : null]);

        return $this->publicUrl('internship.recovery', ['token' => $token]);
    }

    /** @param array<string, int|string> $parameters */
    public function publicUrl(string $name, array $parameters = []): string
    {
        return $this->links->url($name, $parameters);
    }
}
