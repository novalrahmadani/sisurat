<?php

namespace App\Http\Requests;

use App\Models\InternshipParticipant;
use App\Support\InternshipPinAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

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
        $rules['pin'] = ['bail', 'required', 'string', 'regex:/^[0-9]{8}$/D', 'confirmed'];
        $rules['pin_confirmation'] = ['required', 'string', 'regex:/^[0-9]{8}$/D'];

        return $rules;
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $pin = $this->string('pin')->toString();
            if (InternshipParticipant::query()->where('pin_lookup_hash', app(InternshipPinAccess::class)->fingerprint($pin))->exists()) {
                $validator->errors()->add('pin', InternshipPinAccess::UNAVAILABLE_MESSAGE);
            }
        }];
    }

    public function messages(): array
    {
        return [...parent::messages(),
            'pin.required' => 'Buat PIN pribadi untuk akses absensi.',
            'pin.string' => 'PIN harus berupa 8 angka.',
            'pin.regex' => 'PIN harus terdiri dari 8 angka.',
            'pin.confirmed' => 'Konfirmasi PIN harus sama dengan PIN yang Anda buat.',
            'pin_confirmation.required' => 'Ketik ulang PIN Anda sebagai konfirmasi.',
            'pin_confirmation.string' => 'Konfirmasi PIN harus berupa 8 angka.',
            'pin_confirmation.regex' => 'Konfirmasi PIN harus terdiri dari 8 angka.',
        ];
    }
}
