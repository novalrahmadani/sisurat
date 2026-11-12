<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class InternshipRegistrationRequest extends InternshipParticipantRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['is_active']);
        $rules['pin'] = ['required', 'digits:6', 'confirmed'];

        return $rules;
    }

    public function messages(): array
    {
        return [...parent::messages(),
            'pin.required' => 'Buat PIN absensi 6 digit.',
            'pin.digits' => 'PIN absensi harus terdiri dari 6 angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak sama.',
        ];
    }
}
