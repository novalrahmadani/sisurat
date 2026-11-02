<?php

namespace App\Support;

use App\Models\InternshipSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InternshipLocationAccess
{
    /** @return array{configured: bool, radius_meters: int} */
    public function presentation(): array
    {
        $setting = InternshipSetting::current();

        return ['configured' => $setting->latitude !== null && $setting->longitude !== null, 'radius_meters' => $setting->radius_meters];
    }

    public function verify(Request $request): void
    {
        if (! $request->isSecure() && ! (in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true) && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true))) {
            throw ValidationException::withMessages(['location' => 'Absensi GPS harus dibuka melalui alamat HTTPS. Hubungi admin PMB untuk alamat yang sesuai.']);
        }
        $setting = InternshipSetting::current();
        if ($setting->latitude === null || $setting->longitude === null) {
            throw ValidationException::withMessages(['location' => 'Titik lokasi kantor PMB belum ditetapkan. Hubungi admin PMB sebelum melakukan absensi.']);
        }
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'gt:0', 'max:100'],
            'position_captured_at' => ['required', 'integer'],
        ], [
            'latitude.required' => 'Ambil lokasi perangkat sebelum menyimpan absensi.',
            'longitude.required' => 'Ambil lokasi perangkat sebelum menyimpan absensi.',
            'accuracy.max' => 'Lokasi belum cukup akurat. Aktifkan lokasi presisi dan coba lagi di area terbuka dekat kantor PMB.',
            'position_captured_at.required' => 'Ambil ulang lokasi perangkat sebelum menyimpan absensi.',
        ]);
        if (abs(now()->getTimestamp() - (int) $data['position_captured_at']) > 120) {
            throw ValidationException::withMessages(['location' => 'Hasil pembacaan lokasi sudah kedaluwarsa atau jam perangkat tidak sesuai. Aktifkan waktu otomatis lalu coba lagi.']);
        }
        if ((float) $data['accuracy'] > $setting->radius_meters) {
            throw ValidationException::withMessages(['location' => 'Lokasi belum cukup akurat untuk radius kantor PMB. Aktifkan lokasi presisi lalu coba lagi.']);
        }
        $lat1 = deg2rad($setting->latitude);
        $lat2 = deg2rad((float) $data['latitude']);
        $deltaLat = $lat2 - $lat1;
        $deltaLng = deg2rad((float) $data['longitude'] - $setting->longitude);
        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
        $distance = 6371000 * 2 * asin(sqrt(min(1.0, max(0.0, $a))));
        if ($distance > $setting->radius_meters) {
            throw ValidationException::withMessages(['location' => 'Anda berada di luar radius lokasi PMB. Absensi hanya dapat disimpan di area yang telah ditetapkan admin.']);
        }
    }
}
