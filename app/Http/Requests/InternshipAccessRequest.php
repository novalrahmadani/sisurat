<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InternshipAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'], 'radius_meters' => ['required', 'integer', 'between:20,1000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['latitude.required' => 'Isi lintang titik kantor PMB.', 'longitude.required' => 'Isi bujur titik kantor PMB.', 'radius_meters.between' => 'Pilih radius antara 20 hingga 1.000 meter.'];
    }
}
